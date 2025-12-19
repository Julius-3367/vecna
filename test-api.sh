#!/bin/bash

# Vecna ERP API Testing Script
# This script demonstrates how to interact with the API

BASE_URL="http://localhost:8000/api/v1"
TENANT_ID="test-tenant"

echo "======================================"
echo "Vecna ERP API Test Script"
echo "======================================"
echo ""

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${BLUE}1. Health Check${NC}"
echo "GET /up"
curl -s http://localhost:8000/up | jq '.' || echo "OK"
echo ""
echo ""

echo -e "${BLUE}2. Register Tenant${NC}"
echo "POST /api/register-tenant"
REGISTER_RESPONSE=$(curl -s -X POST http://localhost:8000/api/register-tenant \
  -H "Content-Type: application/json" \
  -d '{
    "business_name": "Demo Store",
    "email": "demo@example.com",
    "phone": "254712345678",
    "industry": "retail",
    "subdomain": "demo-store",
    "admin_name": "John Doe",
    "admin_email": "admin@demo.com",
    "admin_password": "password123",
    "admin_password_confirmation": "password123"
  }')

echo "$REGISTER_RESPONSE" | jq '.'
echo ""
echo ""

# Extract tenant ID and token
TENANT_ID=$(echo "$REGISTER_RESPONSE" | jq -r '.data.tenant.id // "test-tenant"')
TOKEN=$(echo "$REGISTER_RESPONSE" | jq -r '.data.token // ""')

if [ -z "$TOKEN" ] || [ "$TOKEN" == "null" ]; then
    echo -e "${YELLOW}Registration failed or tenant already exists. Attempting login...${NC}"
    echo ""
    
    echo -e "${BLUE}3. Login${NC}"
    echo "POST /{tenant}/api/v1/login"
    LOGIN_RESPONSE=$(curl -s -X POST "http://localhost:8000/api/v1/login" \
      -H "Content-Type: application/json" \
      -H "X-Tenant: demo-store" \
      -d '{
        "email": "admin@demo.com",
        "password": "password123"
      }')
    
    echo "$LOGIN_RESPONSE" | jq '.'
    TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.data.token // ""')
    echo ""
    echo ""
fi

if [ -z "$TOKEN" ] || [ "$TOKEN" == "null" ]; then
    echo -e "${YELLOW}Could not get authentication token. Please check tenant setup.${NC}"
    exit 1
fi

echo -e "${GREEN}Authentication successful!${NC}"
echo -e "${GREEN}Token: ${TOKEN:0:20}...${NC}"
echo ""
echo ""

echo -e "${BLUE}4. Create Product${NC}"
echo "POST /{tenant}/api/v1/products"
PRODUCT_RESPONSE=$(curl -s -X POST "http://localhost:8000/api/v1/products" \
  -H "Content-Type: application/json" \
  -H "X-Tenant: demo-store" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "name": "Test Product",
    "sku": "TEST-001",
    "selling_price": 1000,
    "cost_price": 600,
    "stock_quantity": 100,
    "reorder_level": 20,
    "track_stock": true
  }')

echo "$PRODUCT_RESPONSE" | jq '.'
PRODUCT_ID=$(echo "$PRODUCT_RESPONSE" | jq -r '.data.id // ""')
echo ""
echo ""

echo -e "${BLUE}5. List Products${NC}"
echo "GET /{tenant}/api/v1/products"
curl -s "http://localhost:8000/api/v1/products" \
  -H "X-Tenant: demo-store" \
  -H "Authorization: Bearer $TOKEN" | jq '.data.items[0:2]'
echo ""
echo ""

if [ ! -z "$PRODUCT_ID" ] && [ "$PRODUCT_ID" != "null" ]; then
    echo -e "${BLUE}6. Update Product Stock${NC}"
    echo "POST /{tenant}/api/v1/products/{id}/update-stock"
    curl -s -X POST "http://localhost:8000/api/v1/products/$PRODUCT_ID/update-stock" \
      -H "Content-Type: application/json" \
      -H "X-Tenant: demo-store" \
      -H "Authorization: Bearer $TOKEN" \
      -d '{
        "quantity": 10,
        "type": "in",
        "reference": "Stock adjustment test"
      }' | jq '.'
    echo ""
    echo ""
fi

echo -e "${BLUE}7. Create Customer${NC}"
echo "POST /{tenant}/api/v1/customers"
CUSTOMER_RESPONSE=$(curl -s -X POST "http://localhost:8000/api/v1/customers" \
  -H "Content-Type: application/json" \
  -H "X-Tenant: demo-store" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "name": "John Customer",
    "email": "customer@example.com",
    "phone": "254722123456",
    "customer_type": "individual"
  }')

echo "$CUSTOMER_RESPONSE" | jq '.'
CUSTOMER_ID=$(echo "$CUSTOMER_RESPONSE" | jq -r '.data.id // ""')
echo ""
echo ""

if [ ! -z "$PRODUCT_ID" ] && [ "$PRODUCT_ID" != "null" ] && [ ! -z "$CUSTOMER_ID" ] && [ "$CUSTOMER_ID" != "null" ]; then
    echo -e "${BLUE}8. Create Sale${NC}"
    echo "POST /{tenant}/api/v1/sales"
    curl -s -X POST "http://localhost:8000/api/v1/sales" \
      -H "Content-Type: application/json" \
      -H "X-Tenant: demo-store" \
      -H "Authorization: Bearer $TOKEN" \
      -d "{
        \"customer_id\": $CUSTOMER_ID,
        \"items\": [
          {
            \"product_id\": $PRODUCT_ID,
            \"quantity\": 2,
            \"unit_price\": 1000
          }
        ],
        \"payment_method\": \"cash\"
      }" | jq '.'
    echo ""
    echo ""
fi

echo -e "${BLUE}9. Dashboard Stats${NC}"
echo "GET /{tenant}/api/v1/dashboard"
curl -s "http://localhost:8000/api/v1/dashboard" \
  -H "X-Tenant: demo-store" \
  -H "Authorization: Bearer $TOKEN" | jq '.'
echo ""
echo ""

echo -e "${GREEN}======================================"
echo "API Testing Complete!"
echo "======================================${NC}"
echo ""
echo "Server is running at: http://localhost:8000"
echo "You can test the API using:"
echo "  - curl (command line)"
echo "  - Postman (GUI)"
echo "  - Insomnia (GUI)"
echo "  - Thunder Client (VS Code extension)"
echo ""
echo "API Documentation: Check API.md in the project root"
echo ""
