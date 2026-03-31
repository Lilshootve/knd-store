# KND Store – Deployment

## Error: "divergent branches" en git pull

Si el despliegue falla con:
```
fatal: Need to specify how to reconcile divergent branches.
```

### Opción 1: Script (SSH al servidor)

```bash
cd /ruta/a/public_html   # ej: /home/u354862096/domains/kndstore.com/public_html
chmod +x deploy.sh
./deploy.sh main
```

### Opción 2: Comandos manuales (SSH)

```bash
cd /ruta/a/public_html
git config pull.rebase false
git fetch origin
git reset --hard origin/main
```

### Opción 3: Panel de hosting (Hostinger, etc.)

Si usas el botón "Deploy" del panel, normalmente no puedes cambiar el pull. Necesitas:
1. Acceder por SSH al servidor
2. Ir al directorio del proyecto (public_html)
3. Ejecutar los comandos de la Opción 2

---

**Nota:** `git reset --hard origin/main` descarta cambios locales en el servidor. En despliegue, el servidor debe reflejar exactamente el repositorio remoto.
