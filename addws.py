import re
with open('/etc/nginx/conf.d/erden.conf', 'r') as f:
    content = f.read()
proxy = r'''
    # WebSocket proxy para Reverb (Pusher)
    location /app/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 86400;
    }
'''
content = content.replace('access_log /var/log/nginx/erden_access.log;', 'access_log /var/log/nginx/erden_access.log;' + proxy)
with open('/etc/nginx/conf.d/erden.conf', 'w') as f:
    f.write(content)
print('Proxy block added successfully')
