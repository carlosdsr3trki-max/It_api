// servicio-ubicacion/server.js
const { WebSocketServer } = require('ws')
const { createClient } = require('redis')
const http = require('http')

async function main() {
    const redis = createClient({ url: process.env.REDIS_URL })
    await redis.connect()

    const server = http.createServer()
    const wss = new WebSocketServer({ server })

    wss.on('connection', (ws) => {
        let trakerId = null

        ws.on('message', async(raw) => {
            const msg = JSON.parse(raw.toString())

            // Auth
            if (msg.type === 'auth') {
                trakerId = msg.traker_id
                ws.send(JSON.stringify({ type: 'auth_ok' }))
                return
            }

            // Guardar ubicación
            if (msg.type === 'ubicacion' && trakerId) {
                const { lat, lon } = msg
                const payload = JSON.stringify({
                    lat,
                    lon,
                    ts: Date.now()
                })

                // Última posición (expira 5 min)
                await redis.setEx(`ubicacion:${trakerId}`, 300, payload)

                // Historial del día
                const hoy = new Date().toISOString().split('T')[0]
                await redis.lPush(`historial:${trakerId}:${hoy}`, payload)
                await redis.expire(`historial:${trakerId}:${hoy}`, 86400)

                // ✅ Publicar para que servicio-tracking lo reciba
                await redis.publish('ubicacion_nueva', JSON.stringify({
                    traker_id: trakerId,
                    lat,
                    lon,
                    ts: Date.now()
                }))
            }
        })

        ws.on('close', () => {
            if (trakerId) redis.del(`ubicacion:${trakerId}`)
        })
    })

    server.listen(process.env.PORT || 8081)
    console.log('✅ Servicio Ubicacion listo')
}

main().catch(console.error)