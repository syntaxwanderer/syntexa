#!/bin/sh
# RabbitMQ initialization script
# Creates vhosts and sets permissions
# This script runs after RabbitMQ starts

set -e

echo "Waiting for RabbitMQ to be ready..."
# Wait for RabbitMQ to be ready (max 60 seconds)
for i in $(seq 1 30); do
  if rabbitmqctl ping > /dev/null 2>&1; then
    break
  fi
  if [ $i -eq 30 ]; then
    echo "RabbitMQ failed to start after 60 seconds"
    exit 1
  fi
  sleep 2
done

echo "RabbitMQ is ready. Setting up vhosts..."

# Get default user from environment
USER="${RABBITMQ_DEFAULT_USER:-guest}"

# Create production vhost if it doesn't exist
if ! rabbitmqctl list_vhosts 2>/dev/null | grep -q "^/production$"; then
  echo "Creating /production vhost..."
  rabbitmqctl add_vhost /production || echo "Failed to create /production (may already exist)"
  echo "Setting permissions for user $USER on /production..."
  rabbitmqctl set_permissions -p /production "$USER" ".*" ".*" ".*" || echo "Failed to set permissions (may already be set)"
else
  echo "VHost /production already exists"
fi

# Create test vhost if it doesn't exist
if ! rabbitmqctl list_vhosts 2>/dev/null | grep -q "^/test$"; then
  echo "Creating /test vhost..."
  rabbitmqctl add_vhost /test || echo "Failed to create /test (may already exist)"
  echo "Setting permissions for user $USER on /test..."
  rabbitmqctl set_permissions -p /test "$USER" ".*" ".*" ".*" || echo "Failed to set permissions (may already be set)"
else
  echo "VHost /test already exists"
fi

echo "RabbitMQ initialization complete!"

