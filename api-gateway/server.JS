const express = require('express')
const { createProxyMiddleware } = require('http-proxy-middleware')

const app = express()

// Ubicación → servicio-ubicacion
app.use('/ubicacion', createProxyMiddleware({
    target: process.env.URL_SERVICIO_UBICACION,
    changeOrigin: true
}))

// Todo lo demás → tu API PHP actual
app.use('/', createProxyMiddleware({
    target: process.env.URL_API_PHP,
    changeOrigin: true
}))

app.listen(process.env.PORT || 3000)