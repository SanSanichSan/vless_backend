#!/bin/bash
BACKUP_DIR='/root/remnawave/backup'

docker run --rm -v caddy-ssl-data:/data -v ${BACKUP_DIR}:/backup alpine tar -czf /backup/caddy-ssl-data.tar.gz -C /data .
docker run --rm -v remnawave-db-data:/data -v ${BACKUP_DIR}:/backup alpine tar -czf /backup/remnawave-db-data.tar.gz -C /data .
docker run --rm -v remnawave-redis-data:/data -v ${BACKUP_DIR}:/backup alpine tar -czf /backup/remnawave-redis-data.tar.gz -C /data .

