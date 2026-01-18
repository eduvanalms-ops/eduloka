
#!/bin/bash

# Stop existing PHP server
echo "Stopping existing PHP processes..."
pkill -f "php -S" || true
sleep 2

# Clear any port bindings
fuser -k 5000/tcp 2>/dev/null || true
sleep 1

# Start server
echo "Starting PHP server on port 5000..."
php -S 0.0.0.0:5000

