const express = require('express');
const cors = require('cors');
const axios = require('axios');

const app = express();
const API_KEY = 'MAVRO-ESSENCE-SECURE-KEY-2026';
const FIREBASE_URL = 'https://espera-mavro-6ddc5-default-rtdb.asia-southeast1.firebasedatabase.app';
const PORT = process.env.PORT || 3000;

// Middleware
app.use(cors({ 
  origin: '*', 
  methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
  allowedHeaders: ['Content-Type', 'X-API-KEY', 'Authorization']
}));
app.use(express.json({ limit: '50mb' }));

// ============================================
// Firebase REST API Functions
// ============================================
async function firebaseGet(path) {
  try {
    const response = await axios.get(`${FIREBASE_URL}${path}`);
    return response.data;
  } catch (error) {
    console.error('GET Error:', error.message);
    return null;
  }
}

async function firebasePut(path, data) {
  try {
    await axios.put(`${FIREBASE_URL}${path}`, data);
    return true;
  } catch (error) {
    console.error('PUT Error:', error.message);
    return false;
  }
}

async function firebasePost(path, data) {
  try {
    const response = await axios.post(`${FIREBASE_URL}${path}`, data);
    return response.data.name; // Firebase push returns name
  } catch (error) {
    console.error('POST Error:', error.message);
    return null;
  }
}

async function firebasePatch(path, data) {
  try {
    await axios.patch(`${FIREBASE_URL}${path}`, data);
    return true;
  } catch (error) {
    console.error('PATCH Error:', error.message);
    return false;
  }
}

async function firebaseDelete(path) {
  try {
    await axios.delete(`${FIREBASE_URL}${path}`);
    return true;
  } catch (error) {
    console.error('DELETE Error:', error.message);
    return false;
  }
}

// ============================================
// Authentication Middleware
// ============================================
app.use((req, res, next) => {
  // Skip for health check
  if (req.path === '/health' || req.path === '/') {
    return next();
  }

  const apiKey = req.headers['x-api-key'] || req.headers['X-API-KEY'];
  
  if (!apiKey || apiKey !== API_KEY) {
    return res.status(401).json({ 
      status: 'error',
      message: 'Invalid or missing API key' 
    });
  }
  
  next();
});

// ============================================
// Utility Functions
// ============================================
function generateSlug(text) {
  return text
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '');
}

function generateId() {
  return Date.now();
}

// ============================================
// Health Check
// ============================================
app.get('/health', (req, res) => {
  res.status(200).json({
    status: 'ok',
    timestamp: new Date().toISOString(),
    service: 'Mavro Essence API - MoveDrop Integration'
  });
});

// ============================================
// Root Endpoint
// ============================================
app.get('/', (req, res) => {
  res.json({
    name: 'Mavro Essence API',
    version: '3.1.0',
    documentation: 'MoveDrop Custom Channel Integration',
    endpoints: {
      webhooks: 'POST /webhooks',
      categories: 'GET, POST /categories',
      products: 'GET, POST /products',
      variations: 'POST /products/:id/variations',
      orders: 'GET /orders',
      order_status: 'PUT /orders/:id',
      timelines: 'POST /orders/:id/timelines'
    }
  });
});

// ============================================
// WEBHOOKS - POST /webhooks
// ============================================
app.post('/webhooks', async (req, res) => {
  try {
    const { webhooks } = req.body;

    if (!webhooks || !Array.isArray(webhooks) || webhooks.length === 0) {
      return res.status(400).json({ message: 'No webhooks provided' });
    }

    const saved = [];

    for (const webhook of webhooks) {
      const webhookData = {
        name: webhook.name,
        event: webhook.event,
        delivery_url: webhook.delivery_url,
        created_at: new Date().toISOString()
      };

      const key = await firebasePost('/webhooks.json', webhookData);
      
      if (key) {
        saved.push({
          id: key,
          ...webhookData
        });
      }
    }

    res.status(201).json({
      message: 'Webhooks registered successfully',
      data: saved
    });
  } catch (error) {
    console.error('Webhook error:', error);
    res.status(500).json({ message: 'Internal server error' });
  }
});

// ============================================
// CATEGORIES - GET /categories
// ============================================
app.get('/categories', async (req, res) => {
  try {
    const page = parseInt(req.query.page) || 1;
    const perPage = parseInt(req.query.per_page) || 20;
    const offset = (page - 1) * perPage;

    const categoriesData = await firebaseGet('/categories.json') || {};
    
    // Format categories as per MoveDrop spec
    let categories = [];
    
    Object.entries(categoriesData).forEach(([key, category]) => {
      if (category && typeof category === 'object') {
        const name = category.name || 'Unnamed Category';
        categories.push({
          id: parseInt(category.id) || (typeof key === 'string' ? Math.abs(key.split('').reduce((a, b) => a + b.charCodeAt(0), 0)) : parseInt(key)),
          name: name,
          slug: category.slug || generateSlug(name),
          created_at: category.created_at || new Date().toISOString()
        });
      } else if (typeof category === 'string') {
        categories.push({
          id: Math.abs(key.split('').reduce((a, b) => a + b.charCodeAt(0), 0)),
          name: category,
          slug: generateSlug(category),
          created_at: new Date().toISOString()
        });
      }
    });

    // Sort by ID
    categories.sort((a, b) => a.id - b.id);

    // Pagination
    const paginated = categories.slice(offset, offset + perPage);
    const total = categories.length;

    res.status(200).json({
      data: paginated,
      meta: {
        current_page: page,
        from: offset + 1,
        last_page: Math.ceil(total / perPage),
        per_page: perPage,
        to: Math.min(offset + perPage, total),
        total: total
      }
    });
  } catch (error) {
    console.error('Categories GET error:', error);
    res.status(500).json({ message: 'Internal server error' });
  }
});

// ============================================
// CATEGORIES - POST /categories
// ============================================
app.post('/categories', async (req, res) => {
  try {
    const { name } = req.body;

    if (!name || name.trim() === '') {
      return res.status(422).json({
        message: 'The name field is required.',
        errors: { name: ['The name field is required.'] }
      });
    }

    // Check for duplicate
    const existing = await firebaseGet('/categories.json') || {};
    let maxId = 0;

    Object.values(existing).forEach(cat => {
      if (cat && cat.name && cat.name.toLowerCase() === name.toLowerCase()) {
        return res.status(400).json({ message: 'Category with this name already exists' });
      }
      if (cat && cat.id && cat.id > maxId) maxId = cat.id;
    });

    const newId = maxId + 1;
    const timestamp = new Date().toISOString();

    const categoryData = {
      id: newId,
      name: name.trim(),
      slug: generateSlug(name),
      created_at: timestamp
    };

    // Save with ID as key
    const saved = await firebasePut(`/categories/${newId}.json`, categoryData);

    if (saved) {
      res.status(201).json({
        data: categoryData
      });
    } else {
      res.status(500).json({ message: 'Failed to create category' });
    }
  } catch (error) {
    console.error('Categories POST error:', error);
    res.status(500).json({ message: 'Internal server error' });
  }
});

// ============================================
// PRODUCTS - GET /products
// ============================================
app.get('/products', async (req, res) => {
  try {
    const productsData = await firebaseGet('/products.json') || {};
    
    const formatted = Object.entries(productsData).map(([key, product]) => {
      // Process images
      let images = [];
      if (product.images) {
        if (Array.isArray(product.images)) {
          images = product.images.map(img => {
            if (typeof img === 'string') return { is_default: false, src: img };
            return img;
          });
        } else if (typeof product.images === 'object') {
          images = Object.values(product.images).map(img => {
            if (typeof img === 'string') return { is_default: false, src: img };
            return img;
          });
        }
      }

      // Ensure at least one default image
      if (images.length > 0 && !images.some(img => img.is_default)) {
        images[0].is_default = true;
      }

      return {
        id: parseInt(product.id) || (typeof key === 'string' ? Math.abs(key.split('').reduce((a, b) => a + b.charCodeAt(0), 0)) : parseInt(key)),
        title: product.title || product.name || 'Untitled',
        sku: product.sku || '',
        description: product.description || '',
        images: images,
        category_ids: product.category_ids || [],
        tags: product.tags || [],
        properties: product.properties || [],
        created_at: product.created_at || new Date().toISOString(),
        updated_at: product.updated_at || new Date().toISOString()
      };
    });

    res.status(200).json(formatted);
  } catch (error) {
    console.error('Products GET error:', error);
    res.status(500).json({ message: 'Internal server error' });
  }
});

// ============================================
// PRODUCTS - POST /products
// ============================================
app.post('/products', async (req, res) => {
  try {
    const { title, sku, description, images, category_ids, tags, properties } = req.body;

    // Validation
    const errors = {};
    if (!title) errors.title = ['The title field is required.'];
    if (!sku) errors.sku = ['The sku field is required.'];
    if (!images || !Array.isArray(images) || images.length === 0) {
      errors.images = ['At least one image is required.'];
    }

    if (Object.keys(errors).length > 0) {
      return res.status(422).json({
        message: 'Validation failed',
        errors
      });
    }

    // Check duplicate SKU
    const existing = await firebaseGet('/products.json') || {};
    for (const [key, prod] of Object.entries(existing)) {
      if (prod && prod.sku === sku) {
        return res.status(400).json({
          message: 'Product with given SKU already exists',
          data: {
            error: {
              code: 'product_duplicate_sku',
              message: 'SKU already exists.',
              data: {
                product_id: parseInt(key) || Math.abs(key.split('').reduce((a, b) => a + b.charCodeAt(0), 0)),
                sku: sku
              }
            }
          }
        });
      }
    }

    const productId = generateId();
    const timestamp = new Date().toISOString();

    // Process images
    const processedImages = images.map(img => ({
      is_default: img.is_default || false,
      src: img.src
    }));

    // Ensure at least one default
    if (processedImages.length > 0 && !processedImages.some(img => img.is_default)) {
      processedImages[0].is_default = true;
    }

    const productData = {
      id: productId,
      title: title,
      name: title,
      sku: sku,
      description: description || '',
      images: processedImages,
      category_ids: category_ids || [],
      tags: tags || [],
      properties: properties || [],
      created_at: timestamp,
      updated_at: timestamp
    };

    const saved = await firebasePut(`/products/${productId}.json`, productData);

    if (saved) {
      res.status(201).json({
        message: 'Product Created',
        data: {
          id: productId,
          title: title,
          sku: sku,
          tags: tags || [],
          created_at: timestamp,
          updated_at: timestamp
        }
      });
    } else {
      res.status(500).json({ message: 'Failed to create product' });
    }
  } catch (error) {
    console.error('Products POST error:', error);
    res.status(500).json({ message: 'Internal server error' });
  }
});

// ============================================
// PRODUCT VARIATIONS - POST /products/:id/variations
// ============================================
app.post('/products/:id/variations', async (req, res) => {
  try {
    const productId = req.params.id;
    const { variations } = req.body;

    if (!variations || !Array.isArray(variations) || variations.length === 0) {
      return res.status(400).json({ message: 'No variations provided' });
    }

    // Check if product exists
    const product = await firebaseGet(`/products/${productId}.json`);
    if (!product) {
      return res.status(404).json({ message: 'Product not found' });
    }

    const savedVariations = [];
    const existingSkus = new Set();

    // Track existing SKUs
    if (product.variations) {
      Object.values(product.variations).forEach(v => {
        if (v && v.sku) existingSkus.add(v.sku);
      });
    }

    for (let i = 0; i < variations.length; i++) {
      const varData = variations[i];
      const variationId = parseInt(`${productId}${String(i).padStart(2, '0')}`);

      // Check duplicate SKU
      if (existingSkus.has(varData.sku)) {
        savedVariations.push({
          error: {
            code: 'variation_duplicate_sku',
            message: 'SKU already exists.',
            data: {
              variation_id: variationId,
              sku: varData.sku
            }
          }
        });
        continue;
      }

      const variationData = {
        id: variationId,
        sku: varData.sku,
        regular_price: String(varData.regular_price || '0'),
        sale_price: String(varData.sale_price || ''),
        date_on_sale_from: varData.date_on_sale_from || null,
        date_on_sale_to: varData.date_on_sale_to || null,
        stock_quantity: parseInt(varData.stock_quantity) || 0,
        image: varData.image || '',
        properties: varData.properties || []
      };

      // Save variation
      await firebasePut(`/products/${productId}/variations/${i}.json`, variationData);

      savedVariations.push({
        id: variationId,
        sku: varData.sku
      });

      existingSkus.add(varData.sku);
    }

    res.status(201).json({
      message: 'Product Variations Created',
      data: savedVariations
    });
  } catch (error) {
    console.error('Variations POST error:', error);
    res.status(500).json({ message: 'Internal server error' });
  }
});

// ============================================
// DELETE PRODUCT - DELETE /products/:id
// ============================================
app.delete('/products/:id', async (req, res) => {
  try {
    const productId = req.params.id;

    // Check if product exists
    const product = await firebaseGet(`/products/${productId}.json`);
    if (!product) {
      return res.status(404).json({ message: 'Product not found' });
    }

    // Delete product
    const result = await firebaseDelete(`/products/${productId}.json`);

    if (result) {
      res.status(200).json({
        message: 'Product Deleted Successfully'
      });
    } else {
      res.status(500).json({ message: 'Failed to delete product' });
    }
  } catch (error) {
    console.error('Product DELETE error:', error);
    res.status(500).json({ message: 'Internal server error' });
  }
});

// ============================================
// ORDERS - GET /orders
// ============================================
app.get('/orders', async (req, res) => {
  try {
    const page = parseInt(req.query.page) || 1;
    const perPage = parseInt(req.query.per_page) || 20;
    const offset = (page - 1) * perPage;
    const orderNumber = req.query.order_number;

    const ordersData = await firebaseGet('/orders.json') || {};
    
    let formatted = [];

    Object.entries(ordersData).forEach(([key, order]) => {
      // Filter by order number if provided
      if (orderNumber && order.order_number !== orderNumber && key !== orderNumber) {
        return;
      }

      // Format order according to MoveDrop spec
      const customer = order.customer || order.shipping_address || {};
      const payment = order.payment || {};

      formatted.push({
        id: parseInt(key) || Math.abs(key.split('').reduce((a, b) => a + b.charCodeAt(0), 0)),
        order_number: order.order_number || key,
        status: order.status || 'pending',
        currency: order.currency || 'BDT',
        total: String(order.total || '0'),
        payment_method: payment.method || order.payment_method || 'cod',
        shipping_address: {
          first_name: customer.first_name || customer.name || 'Customer',
          last_name: customer.last_name || '',
          email: customer.email || '',
          phone: customer.phone || '',
          address_1: customer.address_1 || customer.address || '',
          address_2: customer.address_2 || '',
          city: customer.city || 'Dhaka',
          state: customer.state || '',
          postcode: customer.postcode || '1200',
          country: customer.country || 'Bangladesh'
        },
        customer_notes: order.customer_notes || order.note || '',
        line_items: (order.line_items || order.items || []).map(item => ({
          id: item.id || generateId(),
          product_id: parseInt(item.product_id) || 0,
          name: item.name || 'Product',
          quantity: parseInt(item.quantity) || 1,
          total: String(item.total || (item.price * item.quantity) || '0'),
          variations: (item.variations || (item.variation ? [item.variation] : [])).map(v => ({
            id: v.id || generateId(),
            variation_id: parseInt(v.variation_id) || 0,
            sku: v.sku || '',
            quantity: parseInt(v.quantity) || 1,
            price: String(v.price || '0'),
            created_at: v.created_at || new Date().toISOString()
          })),
          created_at: item.created_at || new Date().toISOString()
        })),
        created_at: order.created_at || new Date().toISOString()
      });
    });

    // Sort by created_at desc
    formatted.sort((a, b) => (a.created_at < b.created_at ? 1 : -1));

    // Pagination
    const paginated = formatted.slice(offset, offset + perPage);
    const total = formatted.length;

    res.status(200).json({
      data: paginated,
      meta: {
        current_page: page,
        from: offset + 1,
        last_page: Math.ceil(total / perPage),
        per_page: perPage,
        to: Math.min(offset + perPage, total),
        total: total
      }
    });
  } catch (error) {
    console.error('Orders GET error:', error);
    res.status(500).json({ message: 'Internal server error' });
  }
});

// ============================================
// UPDATE ORDER STATUS - PUT /orders/:id
// ============================================
app.put('/orders/:id', async (req, res) => {
  try {
    const orderId = req.params.id;
    const { status } = req.body;

    const validStatuses = ['pending', 'processing', 'completed', 'cancelled'];

    if (!status || !validStatuses.includes(status)) {
      return res.status(422).json({
        message: 'Invalid status',
        errors: { status: ['Status must be one of: ' + validStatuses.join(', ')] }
      });
    }

    // Check if order exists
    const order = await firebaseGet(`/orders/${orderId}.json`);
    if (!order) {
      return res.status(404).json({ message: 'Order not found' });
    }

    // Update order
    const updateData = {
      status: status,
      updated_at: new Date().toISOString()
    };

    // Add to status history
    const statusHistory = order.status_history || [];
    statusHistory.push({
      status: status,
      timestamp: new Date().toISOString(),
      note: `Status updated to ${status}`
    });
    updateData.status_history = statusHistory;

    const result = await firebasePatch(`/orders/${orderId}.json`, updateData);

    if (result) {
      // Get updated order
      const updatedOrder = await firebaseGet(`/orders/${orderId}.json`);
      
      // Format response according to MoveDrop spec
      const customer = updatedOrder.customer || updatedOrder.shipping_address || {};
      const payment = updatedOrder.payment || {};

      const formatted = {
        id: parseInt(orderId) || Math.abs(orderId.split('').reduce((a, b) => a + b.charCodeAt(0), 0)),
        order_number: updatedOrder.order_number || orderId,
        status: updatedOrder.status,
        currency: updatedOrder.currency || 'BDT',
        total: String(updatedOrder.total || '0'),
        payment_method: payment.method || updatedOrder.payment_method || 'cod',
        shipping_address: {
          first_name: customer.first_name || customer.name || 'Customer',
          last_name: customer.last_name || '',
          email: customer.email || '',
          phone: customer.phone || '',
          address_1: customer.address_1 || customer.address || '',
          address_2: customer.address_2 || '',
          city: customer.city || 'Dhaka',
          state: customer.state || '',
          postcode: customer.postcode || '1200',
          country: customer.country || 'Bangladesh'
        },
        customer_notes: updatedOrder.customer_notes || updatedOrder.note || '',
        line_items: (updatedOrder.line_items || updatedOrder.items || []).map(item => ({
          id: item.id || generateId(),
          product_id: parseInt(item.product_id) || 0,
          name: item.name || 'Product',
          quantity: parseInt(item.quantity) || 1,
          total: String(item.total || (item.price * item.quantity) || '0'),
          variations: (item.variations || (item.variation ? [item.variation] : [])).map(v => ({
            id: v.id || generateId(),
            variation_id: parseInt(v.variation_id) || 0,
            sku: v.sku || '',
            quantity: parseInt(v.quantity) || 1,
            price: String(v.price || '0'),
            created_at: v.created_at || new Date().toISOString()
          })),
          created_at: item.created_at || new Date().toISOString()
        })),
        created_at: updatedOrder.created_at || new Date().toISOString()
      };

      res.status(200).json({ data: formatted });
    } else {
      res.status(500).json({ message: 'Failed to update order' });
    }
  } catch (error) {
    console.error('Order PUT error:', error);
    res.status(500).json({ message: 'Internal server error' });
  }
});

// ============================================
// ADD TIMELINE - POST /orders/:id/timelines
// ============================================
app.post('/orders/:id/timelines', async (req, res) => {
  try {
    const orderId = req.params.id;
    const { message } = req.body;

    if (!message) {
      return res.status(422).json({ message: 'Message is required' });
    }

    // Check if order exists
    const order = await firebaseGet(`/orders/${orderId}.json`);
    if (!order) {
      return res.status(404).json({ message: 'Order not found' });
    }

    // Add timeline
    const timelineId = generateId();
    const timelineData = {
      id: timelineId,
      message: message,
      created_at: new Date().toISOString()
    };

    const result = await firebasePut(`/orders/${orderId}/timelines/${timelineId}.json`, timelineData);

    if (result) {
      // Get updated order
      const updatedOrder = await firebaseGet(`/orders/${orderId}.json`);
      
      // Format response
      const customer = updatedOrder.customer || updatedOrder.shipping_address || {};
      const payment = updatedOrder.payment || {};

      const formatted = {
        id: parseInt(orderId) || Math.abs(orderId.split('').reduce((a, b) => a + b.charCodeAt(0), 0)),
        order_number: updatedOrder.order_number || orderId,
        status: updatedOrder.status || 'pending',
        currency: updatedOrder.currency || 'BDT',
        total: String(updatedOrder.total || '0'),
        payment_method: payment.method || updatedOrder.payment_method || 'cod',
        shipping_address: {
          first_name: customer.first_name || customer.name || 'Customer',
          last_name: customer.last_name || '',
          email: customer.email || '',
          phone: customer.phone || '',
          address_1: customer.address_1 || customer.address || '',
          address_2: customer.address_2 || '',
          city: customer.city || 'Dhaka',
          state: customer.state || '',
          postcode: customer.postcode || '1200',
          country: customer.country || 'Bangladesh'
        },
        customer_notes: updatedOrder.customer_notes || updatedOrder.note || '',
        line_items: (updatedOrder.line_items || updatedOrder.items || []).map(item => ({
          id: item.id || generateId(),
          product_id: parseInt(item.product_id) || 0,
          name: item.name || 'Product',
          quantity: parseInt(item.quantity) || 1,
          total: String(item.total || (item.price * item.quantity) || '0'),
          variations: (item.variations || (item.variation ? [item.variation] : [])).map(v => ({
            id: v.id || generateId(),
            variation_id: parseInt(v.variation_id) || 0,
            sku: v.sku || '',
            quantity: parseInt(v.quantity) || 1,
            price: String(v.price || '0'),
            created_at: v.created_at || new Date().toISOString()
          })),
          created_at: item.created_at || new Date().toISOString()
        })),
        created_at: updatedOrder.created_at || new Date().toISOString()
      };

      res.status(200).json({ data: formatted });
    } else {
      res.status(500).json({ message: 'Failed to add timeline' });
    }
  } catch (error) {
    console.error('Timeline POST error:', error);
    res.status(500).json({ message: 'Internal server error' });
  }
});

// ============================================
// 404 Handler
// ============================================
app.use((req, res) => {
  res.status(404).json({
    message: 'Endpoint not found',
    path: req.path,
    method: req.method
  });
});

// ============================================
// Error Handler
// ============================================
app.use((err, req, res, next) => {
  console.error('Unhandled error:', err);
  res.status(500).json({
    message: 'Internal server error',
    error: process.env.NODE_ENV === 'development' ? err.message : undefined
  });
});

// ============================================
// Start Server (for local development)
// ============================================
if (require.main === module) {
  app.listen(PORT, () => {
    console.log('\n=================================');
    console.log('🚀 Mavro Essence API - MoveDrop');
    console.log('=================================');
    console.log(`📍 Local: http://localhost:${PORT}`);
    console.log(`🔑 API Key: ${API_KEY}`);
    console.log('\n📋 Endpoints:');
    console.log('   POST  /webhooks');
    console.log('   GET   /categories');
    console.log('   POST  /categories');
    console.log('   GET   /products');
    console.log('   POST  /products');
    console.log('   POST  /products/:id/variations');
    console.log('   DELETE /products/:id');
    console.log('   GET   /orders');
    console.log('   PUT   /orders/:id');
    console.log('   POST  /orders/:id/timelines');
    console.log('=================================\n');
  });
}

module.exports = app;
