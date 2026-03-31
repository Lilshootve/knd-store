# ComfyUI + Cloudflare Tunnel (Windows)

Configuración para exponer ComfyUI local (127.0.0.1:8188) a KND Store vía Cloudflare Tunnel.

## 1. Instalar cloudflared

```powershell
winget install Cloudflare.cloudflared
```

O descarga desde: https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/

## 2. Iniciar el túnel

```powershell
cloudflared tunnel --url http://127.0.0.1:8188
```

Esto genera una URL temporal tipo `https://xxx-xxx.trycloudflare.com`. Para producción usa un túnel con nombre:

```powershell
cloudflared tunnel login
cloudflared tunnel create comfyui
cloudflared tunnel route dns comfyui comfy.kndstore.com
cloudflared tunnel run comfyui
```

En `~/.cloudflared/config.yml`:

```yaml
tunnel: <TUNNEL_ID>
credentials-file: C:\Users\<tu_user>\.cloudflared\<TUNNEL_ID>.json

ingress:
  - hostname: comfy.kndstore.com
    service: http://127.0.0.1:8188
  - service: http_status:404
```

## 3. Seguridad (opcional)

- **Cloudflare Access**: En Zero Trust > Access > Applications, crea una aplicación para `comfy.kndstore.com` con política (ej. correo verificado, o lista de correos permitidos).
- **Service Token**: Si tu plan lo permite, usa Access Service Auth y configura el header `X-KND-TOKEN` en la política. En KND admin (Labs Settings) define el mismo token.
- **Alternativa**: Mantener el túnel sin DNS público (solo trycloudflare temporal) y usar la URL temporal en Labs Settings para pruebas.

## 4. KND Labs Settings

En Admin > Labs Settings:

- **Provider Mode**: Auto (prueba local primero, fallback a RunPod)
- **Local Base URL**: `https://comfy.kndstore.com`
- **Token**: Si usas Cloudflare Access con Service Token, pon aquí el token compartido. Todas las peticiones de KND a ComfyUI incluirán `X-KND-TOKEN: <token>`.

## 5. Migraciones SQL

```sql
source sql/knd_settings.sql
source sql/knd_labs_jobs_alter_provider.sql
```
