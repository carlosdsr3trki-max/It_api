const express = require('express')
const http = require('http')
const { createProxyMiddleware } = require('http-proxy-middleware')

const app = express()
const server = http.createServer(app)

const PORT = process.env.PORT || 3000
const URL_SERVICIO_UBICACION = process.env.URL_SERVICIO_UBICACION
const URL_API_PHP = process.env.URL_API_PHP

if (!URL_SERVICIO_UBICACION) {
    console.error('Falta variable URL_SERVICIO_UBICACION')
    process.exit(1)
}

if (!URL_API_PHP) {
    console.error('Falta variable URL_API_PHP')
    process.exit(1)
}

console.log('URL_SERVICIO_UBICACION =', URL_SERVICIO_UBICACION)
console.log('URL_API_PHP =', URL_API_PHP)

// Ruta simple para probar que el gateway vive
app.get('/health', (req, res) => {
    res.json({
        ok: true,
        service: 'api-gateway'
    })
})

// Proxy hacia servicio-ubicacion
const ubicacionProxy = createProxyMiddleware({
    target: URL_SERVICIO_UBICACION,
    changeOrigin: true,
    ws: true,
    logLevel: 'debug',
    onError(err, req, res) {
        console.error('Error proxy ubicacion:', err.message)
        if (!res.headersSent) {
            res.status(502).json({
                ok: false,
                error: 'Error conectando con servicio-ubicacion'
            })
        }
    }
})

app.use('/ubicacion', ubicacionProxy)

// Proxy hacia API PHP
app.use('/', createProxyMiddleware({
    target: URL_API_PHP,
    changeOrigin: true,
    logLevel: 'debug',
    onError(err, req, res) {
        console.error('Error proxy api-php:', err.message)
        if (!res.headersSent) {
            res.status(502).json({
                ok: false,
                error: 'Error conectando con api-php'
            })
        }
    }
}))

// Soporte explícito para upgrade de WebSocket
server.on('upgrade', ubicacionProxy.upgrade)

server.listen(PORT, () => {
    console.log(`API Gateway escuchando en puerto ${PORT}`)
})