#!/bin/bash

###############################################################################
# Database Backup Script for Traders Point Academy
# Add to crontab: 0 2 * * * /path/to/backup-database.sh
###############################################################################

# Configuration
BACKUP_DIR="/path/to/backups/database"
DB_NAME="traders_point"
DB_USER="root"
DB_PASS="your_password"
RETENTION_DAYS=30

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Generate filename with timestamp
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="$BACKUP_DIR/traders_point_${TIMESTAMP}.sql.gz"

# Perform backup
echo "Starting database backup..."
mysqldump -u "$DB_USER" -p"$DB_PASS" \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    "$DB_NAME" | gzip > "$BACKUP_FILE"

# Check if backup was successful
if [ $? -eq 0 ]; then
    echo "✅ Backup completed successfully: $BACKUP_FILE"
    
    # Get backup file size
    SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    echo "📦 Backup size: $SIZE"
    
    # Delete backups older than retention period
    echo "🧹 Cleaning up old backups (older than $RETENTION_DAYS days)..."
    find "$BACKUP_DIR" -name "traders_point_*.sql.gz" -type f -mtime +$RETENTION_DAYS -delete
    
    echo "✅ Backup process completed!"
else
    echo "❌ Backup failed!"
    exit 1
fi

# Optional: Upload to S3 for off-site backup
# Uncomment and configure if using AWS S3
# aws s3 cp "$BACKUP_FILE" "s3://your-bucket/database-backups/"
