// server.js  ← UN solo archivo, UN solo servicio en Railway
const express = require('express')
const http = require('http')
const { WebSocketServer } = require('ws')
const { createClient } = require('redis')
const { createProxyMiddleware } = require('http-proxy-middleware')

// ─── Variables de entorno (Railway las inyecta) ───────────────
const PORT = process.env.PORT || 3000
const URL_API_PHP = process.env.URL_API_PHP // tu servicio PHP en Railway
const REDIS_URL = process.env.REDIS_URL // ${{Redis.REDIS_URL}}

// ─── Redis ────────────────────────────────────────────────────
const redis = createClient({ url: REDIS_URL })
const redisSub = redis.duplicate() // Pub/Sub necesita cliente separado

// ─── Express + HTTP + WebSocket ───────────────────────────────
const app = express()
const server = http.createServer(app)
const wss = new WebSocketServer({ noServer: true })

async function main() {
    await redis.connect()
    await redisSub.connect()
    console.log('✅ Redis conectado')

    // ── Health check ─────────────────────────────────────────
    app.get('/health', (req, res) => res.json({ ok: true }))

    // ── Rutas de consulta rápida desde Redis (sin tocar PHP) ──
    app.get('/ubicacion/:trakerId', async(req, res) => {
        const data = await redis.get(`ubicacion:${req.params.trakerId}`)
        if (!data) return res.status(404).json({ status: 'not_found' })
        res.json(JSON.parse(data))
    })

    app.get('/historial/:trakerId', async(req, res) => {
        const hoy = new Date().toISOString().split('T')[0]
        const hist = await redis.lRange(`historial:${req.params.trakerId}:${hoy}`, 0, -1)
        res.json(hist.map(JSON.parse))
    })

    // ── Proxy al PHP para todo lo demás ──────────────────────
    app.use('/', createProxyMiddleware({
        target: URL_API_PHP,
        changeOrigin: true,
        on: {
            error: (err, req, res) => {
                console.error('Error proxy php:', err.message)
                res.status(502).json({ ok: false, error: 'PHP service error' })
            }
        }
    }))

    // ── WebSocket: recibe ubicaciones del APK ─────────────────
    wss.on('connection', (ws) => {
        let trakerId = null

        ws.on('message', async(raw) => {
            let msg
            try { msg = JSON.parse(raw.toString()) } catch { return }

            // Auth
            if (msg.type === 'auth') {
                trakerId = msg.traker_id
                ws.send(JSON.stringify({ type: 'auth_ok' }))
                return
            }

            // Guardar ubicación
            if (msg.type === 'ubicacion' && trakerId) {
                const { lat, lon } = msg
                const payload = JSON.stringify({ lat, lon, ts: Date.now() })

                // Última posición (expira 5 min)
                await redis.set(`ubicacion:${trakerId}`, payload)
                    // Historial del día (expira 24h)
                const hoy = new Date().toISOString().split('T')[0]
                await redis.lPush(`historial:${trakerId}:${hoy}`, payload)
                await redis.expire(`historial:${trakerId}:${hoy}`, 86400)

                // Marcar para flush a MySQL
                await redis.sAdd('devices:dirty', trakerId)
                await redis.hSet(`device:${trakerId}`, { lat, lon, updated_at: Date.now() })
                await redis.expire(`device:${trakerId}`, 600)
                    // Broadcast a todos los clientes conectados
                wss.clients.forEach(client => {
                    if (client.readyState === 1) {
                        client.send(JSON.stringify({
                            type: 'ubicacion_update',
                            traker_id: trakerId,
                            lat,
                            lon
                        }))
                    }
                })
            }
        })

        ws.on('close', () => {
            console.log(`Cliente desconectado: ${trakerId}`)
        })
    })

    // ── Upgrade HTTP → WebSocket ───────────────────────────────
    server.on('upgrade', (req, socket, head) => {
        // Solo el path /ws va a WebSocket
        if (req.url === '/ws') {
            wss.handleUpgrade(req, socket, head, ws => {
                wss.emit('connection', ws, req)
            })
        } else {
            socket.destroy()
        }
    })

    // ── Flush Redis → MySQL cada 30 segundos ──────────────────
    setInterval(flushToMySQL, 100000)

    server.listen(PORT, () => console.log(`🚀 Server en puerto ${PORT}`))
}

// ─── Worker de flush integrado (ya no necesitas cron externo) ─
async function flushToMySQL() {
    const devices = await redis.sMembers('devices:dirty')
    if (!devices.length) return

    await redis.del('devices:dirty')

    const batch = []

    for (const id of devices) {
        const data = await redis.hGetAll(`device:${id}`)

        if (!data.lat || !data.lon) continue

        batch.push({
            traker_id: id,
            lat: data.lat,
            lon: data.lon
        })
    }

    if (!batch.length) return

    try {
        const response = await fetch(`${URL_API_PHP}/guardar_batch_ubicaciones.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                ubicaciones: batch
            })
        })

        const text = await response.text()
        console.log('Batch MySQL response:', text)

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${text}`)
        }

        console.log(`✔ Batch guardado: ${batch.length} ubicaciones`)

    } catch (e) {
        console.error('✗ Error batch flush:', e.message)

        // Si falla, regresamos los dispositivos a pendientes
        for (const item of batch) {
            await redis.sAdd('devices:dirty', item.traker_id)
        }
    }
}

main().catch(console.error)