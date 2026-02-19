const express = require('express');
const cors = require('cors');
const admin = require('firebase-admin');
const crypto = require('crypto');

// ============================================
// কনফিগারেশন
// ============================================
const API_KEY = 'MAVRO-ESSENCE-SECURE-KEY-2026';
const FIREBASE_URL = 'https://espera-mavro-6ddc5-default-rtdb.asia-southeast1.firebasedatabase.app';
const ITEMS_PER_PAGE = 20;

// ============================================
// Firebase অ্যাডমিন ইনিশিয়ালাইজ
// ============================================
if (!admin.apps.length) {
  try {
    // প্রোডাকশনে (Vercel) environment variable ব্যবহার করবে
    if (process.env.FIREBASE_SERVICE_ACCOUNT) {
      const serviceAccount = JSON.parse(process.env.FIREBASE_SERVICE_ACCOUNT);
      admin.initializeApp({
        credential: admin.credential.cert(serviceAccount),
        databaseURL: FIREBASE_URL
      });
    } else {
      // লোকাল ডেভেলপমেন্টের জন্য
      admin.initializeApp({
        credential: admin.credential.applicationDefault(),
        databaseURL: FIREBASE_URL
      });
    }
    console.log('✅ Firebase initialized successfully');
  } catch (error) {
    console.error('❌ Firebase initialization error:', error);
  }
}

const db = admin.database();
const app = express();

// ============================================
// মিডলওয়্যার
// ============================================
app.use(cors({
  origin: '*',
  methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
  allowedHeaders: ['Content-Type', 'X-API-KEY', 'Authorization', 'Accept']
}));

app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true, limit: '10mb' }));

// ============================================
// অথেনটিকেশন মিডলওয়্যার
// ============================================
function authenticate(req, res, next) {
  // হেলথ চেকের জন্য অথেনটিকেশন লাগবে না
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
}

// সব রাউটে অথেনটিকেশন অ্যাপ্লাই করুন
app.use(authenticate);

// ============================================
// ফায়ারবেস হেল্পার ফাংশন
// ============================================
async function firebaseGet(path) {
  try {
    const ref = db.ref(path);
    const snapshot = await ref.once('value');
    return snapshot.val();
  } catch (error) {
    console.error('❌ Firebase GET Error:', error.message);
    return null;
  }
}

async function firebasePut(path, data) {
  try {
    const ref = db.ref(path);
    await ref.set(data);
    return true;
  } catch (error) {
    console.error('❌ Firebase PUT Error:', error.message);
    return false;
  }
}

async function firebasePatch(path, data) {
  try {
    const ref = db.ref(path);
    await ref.update(data);
    return true;
  } catch (error) {
    console.error('❌ Firebase PATCH Error:', error.message);
    return false;
  }
}

async function firebaseDelete(path) {
  try {
    const ref = db.ref(path);
    await ref.remove();
    return true;
  } catch (error) {
    console.error('❌ Firebase DELETE Error:', error.message);
    return false;
  }
}

async function firebasePush(path, data) {
  try {
    const ref = db.ref(path);
    const newRef = ref.push();
    await newRef.set(data);
    return newRef.key;
  } catch (error) {
    console.error('❌ Firebase PUSH Error:', error.message);
    return null;
  }
}

// ============================================
// ইউটিলিটি ফাংশন
// ============================================
function generateSlug(string) {
  return string
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '');
}

function generateOrderId() {
  const timestamp = Date.now().toString().slice(-8);
  const random = Math.random().toString(36).substring(2, 6).toUpperCase();
  return `ORD-${timestamp}-${random}`;
}

function hashString(str) {
  return crypto.createHash('md5').update(str).digest('hex');
}

function formatProduct(id, product) {
  // ইমেজ প্রসেসিং
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
  } else if (product.image) {
    images = [{ is_default: true, src: product.image }];
  }

  // ডিফল্ট ইমেজ সেট করুন
  if (images.length > 0 && !images.some(img => img.is_default)) {
    images[0].is_default = true;
  }

  // প্রাইস এক্সট্রাক্ট করুন
  let price = 0;
  if (product.sale_price) price = parseFloat(product.sale_price);
  else if (product.price) price = parseFloat(product.price);
  else if (product.regular_price) price = parseFloat(product.regular_price);

  return {
    id: product.id || (typeof id === 'string' ? id : String(id)),
    title: product.title || product.name || 'Untitled Product',
    name: product.name || product.title || 'Untitled Product',
    sku: product.sku || '',
    description: product.description || '',
    price: price,
    images: images,
    image: images.length > 0 ? images[0].src : null,
    category_ids: product.category_ids || [],
    category: product.category || null,
    tags: product.tags || [],
    properties: product.properties || [],
    variations: product.variations || null,
    created_at: product.created_at || new Date().toISOString(),
    updated_at: product.updated_at || new Date().toISOString()
  };
}

function formatOrder(id, order) {
  const customer = order.customer || order.shipping_address || {};
  const payment = order.payment || {};
  const items = order.items || order.line_items || [];

  return {
    id: id,
    order_id: id,
    order_number: order.order_number || order.order_id || id,
    status: order.status || 'pending',
    currency: order.currency || 'BDT',
    subtotal: parseFloat(order.subtotal || 0),
    delivery_charge: parseFloat(order.delivery_charge || 0),
    total: parseFloat(order.total || 0),
    payment: {
      method: payment.method || 'cod',
      status: payment.status || (payment.method === 'cod' ? 'pending' : 'awaiting_verification'),
      trxId: payment.trxId || payment.transaction_id || null,
      number: payment.number || null,
      verified_at: payment.verified_at || null
    },
    customer: {
      name: customer.name || customer.first_name || 'Customer',
      phone: customer.phone || '',
      email: customer.email || '',
      address: customer.address || customer.address_1 || '',
      city: customer.city || 'Dhaka',
      postcode: customer.postcode || '1200'
    },
    note: order.note || order.customer_notes || '',
    items: items.map(item => ({
      id: item.id || Date.now(),
      product_id: item.product_id || null,
      name: item.name || 'Product',
      price: parseFloat(item.price || 0),
      quantity: parseInt(item.quantity || 1),
      image: item.image || null,
      variation: item.variation || null
    })),
    status_history: order.status_history || [
      {
        status: order.status || 'pending',
        timestamp: order.created_at || new Date().toISOString(),
        note: 'Order placed'
      }
    ],
    created_at: order.created_at || new Date().toISOString(),
    updated_at: order.updated_at || new Date().toISOString()
  };
}

// ============================================
// রুট - হোম পেজ
// ============================================
app.get('/', (req, res) => {
  res.json({
    name: 'Mavro Essence API',
    version: '3.1.0',
    description: 'MoveDrop Integration API - Node.js Version',
    endpoints: {
      health: '/health',
      webhooks: '/webhooks',
      categories: '/categories',
      products: '/products',
      orders: '/orders'
    },
    documentation: 'See README for more details'
  });
});

// ============================================
// হেলথ চেক
// ============================================
app.get('/health', (req, res) => {
  res.json({
    status: 'ok',
    timestamp: new Date().toISOString(),
    service: 'Mavro Essence API - Node.js',
    environment: process.env.NODE_ENV || 'development',
    firebase: db ? 'connected' : 'disconnected'
  });
});

// ============================================
// ওয়েবহুক রেজিস্ট্রেশন
// ============================================
app.post('/webhooks', async (req, res) => {
  try {
    const { webhooks } = req.body;

    if (!webhooks || !Array.isArray(webhooks) || webhooks.length === 0) {
      return res.status(400).json({ 
        message: 'No webhooks provided',
        error: 'webhooks array is required'
      });
    }

    const saved = [];

    for (const webhook of webhooks) {
      if (!webhook.event || !webhook.delivery_url) {
        continue;
      }

      const webhookData = {
        name: webhook.name || `Webhook for ${webhook.event}`,
        event: webhook.event,
        delivery_url: webhook.delivery_url,
        created_at: new Date().toISOString()
      };

      const key = await firebasePush('/webhooks', webhookData);
      saved.push({
        id: key,
        ...webhookData
      });
    }

    res.status(201).json({
      message: 'Webhooks registered successfully',
      data: saved
    });
  } catch (error) {
    console.error('Webhook registration error:', error);
    res.status(500).json({ message: 'Failed to register webhooks' });
  }
});

// ============================================
// ক্যাটাগরি এন্ডপয়েন্ট
// ============================================

// GET /categories - সব ক্যাটাগরি দেখান
app.get('/categories', async (req, res) => {
  try {
    const page = parseInt(req.query.page) || 1;
    const perPage = parseInt(req.query.per_page) || ITEMS_PER_PAGE;
    const offset = (page - 1) * perPage;

    const categoriesData = await firebaseGet('/categories') || {};
    let categories = [];

    // ক্যাটাগরি ফরম্যাট করুন
    Object.entries(categoriesData).forEach(([key, category]) => {
      if (category && typeof category === 'object') {
        const name = category.name || (typeof category === 'string' ? category : 'Unnamed');
        categories.push({
          id: key,
          name: name,
          slug: category.slug || generateSlug(name),
          created_at: category.created_at || new Date().toISOString()
        });
      } else if (typeof category === 'string') {
        categories.push({
          id: key,
          name: category,
          slug: generateSlug(category),
          created_at: new Date().toISOString()
        });
      }
    });

    // আইডি অনুযায়ী সাজান
    categories.sort((a, b) => (a.id > b.id ? 1 : -1));

    // প্যাজিনেশন
    const paginated = categories.slice(offset, offset + perPage);
    const total = categories.length;

    res.json({
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
    console.error('Error fetching categories:', error);
    res.status(500).json({ message: 'Failed to fetch categories' });
  }
});

// POST /categories - নতুন ক্যাটাগরি তৈরি
app.post('/categories', async (req, res) => {
  try {
    const { name } = req.body;

    if (!name || name.trim() === '') {
      return res.status(422).json({
        message: 'The name field is required.',
        errors: { name: ['The name field is required.'] }
      });
    }

    // ডুপ্লিকেট চেক
    const existing = await firebaseGet('/categories') || {};
    let isDuplicate = false;

    Object.values(existing).forEach(cat => {
      if (cat && cat.name && cat.name.toLowerCase() === name.toLowerCase()) {
        isDuplicate = true;
      }
    });

    if (isDuplicate) {
      return res.status(400).json({
        message: 'Category with this name already exists'
      });
    }

    // নতুন ক্যাটাগরি ডাটা
    const categoryData = {
      name: name.trim(),
      slug: generateSlug(name),
      created_at: new Date().toISOString()
    };

    // ফায়ারবেসে সেভ
    const key = await firebasePush('/categories', categoryData);

    if (key) {
      res.status(201).json({
        data: {
          id: key,
          ...categoryData
        }
      });
    } else {
      res.status(500).json({ message: 'Failed to create category' });
    }
  } catch (error) {
    console.error('Error creating category:', error);
    res.status(500).json({ message: 'Failed to create category' });
  }
});

// ============================================
// প্রোডাক্ট এন্ডপয়েন্ট
// ============================================

// GET /products - সব প্রোডাক্ট দেখান
app.get('/products', async (req, res) => {
  try {
    const productsData = await firebaseGet('/products') || {};
    
    const formatted = Object.entries(productsData).map(([key, product]) => 
      formatProduct(key, product)
    );

    res.json(formatted);
  } catch (error) {
    console.error('Error fetching products:', error);
    res.status(500).json({ message: 'Failed to fetch products' });
  }
});

// GET /products/:id - নির্দিষ্ট প্রোডাক্ট দেখান
app.get('/products/:id', async (req, res) => {
  try {
    const { id } = req.params;
    const product = await firebaseGet(`/products/${id}`);

    if (!product) {
      return res.status(404).json({ message: 'Product not found' });
    }

    res.json(formatProduct(id, product));
  } catch (error) {
    console.error('Error fetching product:', error);
    res.status(500).json({ message: 'Failed to fetch product' });
  }
});

// POST /products - নতুন প্রোডাক্ট তৈরি
app.post('/products', async (req, res) => {
  try {
    const { title, sku, description, images, category_ids, tags, properties, price } = req.body;

    // ভ্যালিডেশন
    const errors = {};
    if (!title) errors.title = ['The title field is required.'];
    if (!sku) errors.sku = ['The sku field is required.'];
    
    if (!images || !Array.isArray(images) || images.length === 0) {
      if (!req.body.image) {
        errors.images = ['At least one image is required.'];
      }
    }

    if (Object.keys(errors).length > 0) {
      return res.status(422).json({
        message: 'Validation failed',
        errors
      });
    }

    // ডুপ্লিকেট SKU চেক
    const existing = await firebaseGet('/products') || {};
    for (const [key, prod] of Object.entries(existing)) {
      if (prod && prod.sku === sku) {
        return res.status(400).json({
          message: 'Product with given SKU already exists',
          data: {
            error: {
              code: 'product_duplicate_sku',
              message: 'SKU already exists.',
              data: { product_id: key, sku }
            }
          }
        });
      }
    }

    const timestamp = new Date().toISOString();

    // ইমেজ প্রসেসিং
    let productImages = [];
    if (images && Array.isArray(images)) {
      productImages = images.map(img => ({
        is_default: img.is_default || false,
        src: img.src
      }));
    } else if (req.body.image) {
      productImages = [{ is_default: true, src: req.body.image }];
    }

    // ডিফল্ট ইমেজ সেট
    if (productImages.length > 0 && !productImages.some(img => img.is_default)) {
      productImages[0].is_default = true;
    }

    // প্রোডাক্ট ডাটা
    const productData = {
      title: title,
      name: title,
      sku: sku,
      description: description || '',
      price: parseFloat(price || 0),
      images: productImages,
      image: productImages.length > 0 ? productImages[0].src : null,
      category_ids: category_ids || [],
      tags: tags || [],
      properties: properties || [],
      created_at: timestamp,
      updated_at: timestamp
    };

    // ফায়ারবেসে সেভ
    const key = await firebasePush('/products', productData);

    if (key) {
      res.status(201).json({
        message: 'Product Created',
        data: {
          id: key,
          title: productData.title,
          sku: productData.sku,
          tags: productData.tags,
          created_at: timestamp,
          updated_at: timestamp
        }
      });
    } else {
      res.status(500).json({ message: 'Failed to create product' });
    }
  } catch (error) {
    console.error('Error creating product:', error);
    res.status(500).json({ message: 'Failed to create product' });
  }
});

// POST /products/:id/variations - ভ্যারিয়েশন তৈরি
app.post('/products/:id/variations', async (req, res) => {
  try {
    const productId = req.params.id;
    const { variations } = req.body;

    if (!variations || !Array.isArray(variations) || variations.length === 0) {
      return res.status(400).json({ message: 'No variations provided' });
    }

    // প্রোডাক্ট আছে কিনা চেক
    const product = await firebaseGet(`/products/${productId}`);
    if (!product) {
      return res.status(404).json({ message: 'Product not found' });
    }

    const savedVariations = [];
    const existingSkus = new Set();

    // আগের ভ্যারিয়েশনগুলোর SKU ট্র্যাক করুন
    if (product.variations) {
      Object.values(product.variations).forEach(v => {
        if (v && v.sku) existingSkus.add(v.sku);
      });
    }

    // প্রতিটি ভ্যারিয়েশন সেভ করুন
    for (let i = 0; i < variations.length; i++) {
      const varData = variations[i];

      // ডুপ্লিকেট SKU চেক
      if (existingSkus.has(varData.sku)) {
        savedVariations.push({
          error: {
            code: 'variation_duplicate_sku',
            message: 'SKU already exists.',
            data: { variation_id: `${productId}_${i}`, sku: varData.sku }
          }
        });
        continue;
      }

      const variation = {
        id: `${productId}_${i}`,
        sku: varData.sku,
        regular_price: String(varData.regular_price || '0'),
        sale_price: String(varData.sale_price || ''),
        date_on_sale_from: varData.date_on_sale_from || null,
        date_on_sale_to: varData.date_on_sale_to || null,
        stock_quantity: parseInt(varData.stock_quantity) || 0,
        image: varData.image || '',
        properties: varData.properties || []
      };

      // ভ্যারিয়েশন সেভ
      await firebasePut(`/products/${productId}/variations/${i}`, variation);

      savedVariations.push({
        id: variation.id,
        sku: varData.sku
      });

      existingSkus.add(varData.sku);
    }

    res.status(201).json({
      message: 'Product Variations Created',
      data: savedVariations
    });
  } catch (error) {
    console.error('Error creating variations:', error);
    res.status(500).json({ message: 'Failed to create variations' });
  }
});

// DELETE /products/:id - প্রোডাক্ট ডিলিট
app.delete('/products/:id', async (req, res) => {
  try {
    const productId = req.params.id;

    // প্রোডাক্ট আছে কিনা চেক
    const product = await firebaseGet(`/products/${productId}`);
    if (!product) {
      return res.status(404).json({ message: 'Product not found' });
    }

    // প্রোডাক্ট ডিলিট
    const result = await firebaseDelete(`/products/${productId}`);

    if (result) {
      res.json({ message: 'Product Deleted Successfully' });
    } else {
      res.status(500).json({ message: 'Failed to delete product' });
    }
  } catch (error) {
    console.error('Error deleting product:', error);
    res.status(500).json({ message: 'Failed to delete product' });
  }
});

// ============================================
// অর্ডার এন্ডপয়েন্ট
// ============================================

// GET /orders - সব অর্ডার দেখান
app.get('/orders', async (req, res) => {
  try {
    const page = parseInt(req.query.page) || 1;
    const perPage = parseInt(req.query.per_page) || ITEMS_PER_PAGE;
    const offset = (page - 1) * perPage;
    const orderNumber = req.query.order_number;

    const ordersData = await firebaseGet('/orders') || {};
    
    let formatted = [];
    Object.entries(ordersData).forEach(([key, order]) => {
      if (orderNumber && order.order_number !== orderNumber && key !== orderNumber) {
        return;
      }
      formatted.push(formatOrder(key, order));
    });

    // ডেট অনুযায়ী সাজান (নতুন প্রথমে)
    formatted.sort((a, b) => (a.created_at < b.created_at ? 1 : -1));

    // প্যাজিনেশন
    const paginated = formatted.slice(offset, offset + perPage);
    const total = formatted.length;

    res.json({
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
    console.error('Error fetching orders:', error);
    res.status(500).json({ message: 'Failed to fetch orders' });
  }
});

// GET /orders/:id - নির্দিষ্ট অর্ডার দেখান
app.get('/orders/:id', async (req, res) => {
  try {
    const orderId = req.params.id;
    const order = await firebaseGet(`/orders/${orderId}`);

    if (!order) {
      return res.status(404).json({ message: 'Order not found' });
    }

    res.json(formatOrder(orderId, order));
  } catch (error) {
    console.error('Error fetching order:', error);
    res.status(500).json({ message: 'Failed to fetch order' });
  }
});

// POST /orders - নতুন অর্ডার তৈরি (ফ্রন্টএন্ড থেকে)
app.post('/orders', async (req, res) => {
  try {
    const orderData = req.body;
    
    // অর্ডার আইডি জেনারেট
    const orderId = orderData.order_id || orderData.order_number || generateOrderId();

    // ডাটা ফরম্যাট
    const formattedOrder = formatOrder(orderId, {
      ...orderData,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    });

    // ফায়ারবেসে সেভ
    const saved = await firebasePut(`/orders/${orderId}`, formattedOrder);

    if (saved) {
      res.status(201).json({
        message: 'Order created successfully',
        data: formattedOrder
      });
    } else {
      res.status(500).json({ message: 'Failed to create order' });
    }
  } catch (error) {
    console.error('Error creating order:', error);
    res.status(500).json({ message: 'Failed to create order' });
  }
});

// PUT /orders/:id - অর্ডার স্টেটাস আপডেট
app.put('/orders/:id', async (req, res) => {
  try {
    const orderId = req.params.id;
    const { status, note } = req.body;

    const validStatuses = ['pending', 'placed', 'processing', 'shipping', 'completed', 'cancelled'];

    if (!status || !validStatuses.includes(status)) {
      return res.status(422).json({
        message: 'Invalid status',
        errors: { 
          status: ['Status must be one of: ' + validStatuses.join(', ')] 
        }
      });
    }

    // অর্ডার আছে কিনা চেক
    const order = await firebaseGet(`/orders/${orderId}`);
    if (!order) {
      return res.status(404).json({ message: 'Order not found' });
    }

    // স্টেটাস হিস্ট্রি আপডেট
    const statusHistory = order.status_history || [];
    statusHistory.push({
      status: status,
      timestamp: new Date().toISOString(),
      note: note || `Status updated to ${status}`
    });

    // অর্ডার আপডেট
    const updateData = {
      status: status,
      updated_at: new Date().toISOString(),
      status_history: statusHistory
    };

    const result = await firebasePatch(`/orders/${orderId}`, updateData);

    if (result) {
      const updatedOrder = await firebaseGet(`/orders/${orderId}`);
      res.json({ 
        message: 'Order updated successfully',
        data: formatOrder(orderId, updatedOrder) 
      });
    } else {
      res.status(500).json({ message: 'Failed to update order' });
    }
  } catch (error) {
    console.error('Error updating order:', error);
    res.status(500).json({ message: 'Failed to update order' });
  }
});

// POST /orders/:id/timelines - টাইমলাইন অ্যাড
app.post('/orders/:id/timelines', async (req, res) => {
  try {
    const orderId = req.params.id;
    const { message } = req.body;

    if (!message) {
      return res.status(422).json({ message: 'Message is required' });
    }

    // অর্ডার আছে কিনা চেক
    const order = await firebaseGet(`/orders/${orderId}`);
    if (!order) {
      return res.status(404).json({ message: 'Order not found' });
    }

    // টাইমলাইন অ্যাড
    const timelineId = Date.now();
    const timelineData = {
      id: timelineId,
      message: message,
      created_at: new Date().toISOString()
    };

    // টাইমলাইন সেভ
    await firebasePut(`/orders/${orderId}/timelines/${timelineId}`, timelineData);

    // আপডেটেড অর্ডার রিটার্ন
    const updatedOrder = await firebaseGet(`/orders/${orderId}`);
    res.json({ 
      message: 'Timeline added successfully',
      data: formatOrder(orderId, updatedOrder) 
    });
  } catch (error) {
    console.error('Error adding timeline:', error);
    res.status(500).json({ message: 'Failed to add timeline' });
  }
});

// ============================================
// পেমেন্ট ভেরিফিকেশন এন্ডপয়েন্ট (অ্যাডমিনের জন্য)
// ============================================
app.post('/orders/:id/verify-payment', async (req, res) => {
  try {
    const orderId = req.params.id;

    // অর্ডার আছে কিনা চেক
    const order = await firebaseGet(`/orders/${orderId}`);
    if (!order) {
      return res.status(404).json({ message: 'Order not found' });
    }

    // স্টেটাস হিস্ট্রি
    const statusHistory = order.status_history || [];
    statusHistory.push({
      status: 'placed',
      timestamp: new Date().toISOString(),
      note: `Payment verified. Transaction ID: ${order.payment?.trxId || 'N/A'}`
    });

    // অর্ডার আপডেট
    const updateData = {
      'payment.status': 'paid',
      'payment.verified_at': new Date().toISOString(),
      status: 'placed',
      updated_at: new Date().toISOString(),
      status_history: statusHistory
    };

    const result = await firebasePatch(`/orders/${orderId}`, updateData);

    if (result) {
      const updatedOrder = await firebaseGet(`/orders/${orderId}`);
      res.json({ 
        message: 'Payment verified successfully',
        data: formatOrder(orderId, updatedOrder) 
      });
    } else {
      res.status(500).json({ message: 'Failed to verify payment' });
    }
  } catch (error) {
    console.error('Error verifying payment:', error);
    res.status(500).json({ message: 'Failed to verify payment' });
  }
});

// ============================================
// পরিসংখ্যান এন্ডপয়েন্ট (ড্যাশবোর্ডের জন্য)
// ============================================
app.get('/stats', async (req, res) => {
  try {
    const products = await firebaseGet('/products') || {};
    const orders = await firebaseGet('/orders') || {};

    let totalOrders = 0;
    let totalRevenue = 0;
    let pendingVerification = 0;
    let processingCount = 0;
    let completedCount = 0;
    let todayOrders = 0;

    const today = new Date().toDateString();

    Object.values(orders).forEach(order => {
      totalOrders++;
      
      if (order.payment?.status === 'awaiting_verification') pendingVerification++;
      if (order.status === 'processing') processingCount++;
      if (order.status === 'completed') {
        completedCount++;
        totalRevenue += parseFloat(order.total || 0);
      }
      
      if (order.created_at && new Date(order.created_at).toDateString() === today) {
        todayOrders++;
      }
    });

    res.json({
      total_products: Object.keys(products).length,
      total_orders: totalOrders,
      pending_verification: pendingVerification,
      processing: processingCount,
      completed: completedCount,
      total_revenue: totalRevenue,
      today_orders: todayOrders,
      average_order_value: totalOrders > 0 ? (totalRevenue / totalOrders) : 0
    });
  } catch (error) {
    console.error('Error fetching stats:', error);
    res.status(500).json({ message: 'Failed to fetch stats' });
  }
});

// ============================================
// 404 হ্যান্ডলার
// ============================================
app.use((req, res) => {
  res.status(404).json({
    message: 'Endpoint not found',
    path: req.path,
    method: req.method,
    available_endpoints: [
      '/',
      '/health',
      '/webhooks',
      '/categories',
      '/products',
      '/products/:id',
      '/products/:id/variations',
      '/orders',
      '/orders/:id',
      '/orders/:id/timelines',
      '/orders/:id/verify-payment',
      '/stats'
    ]
  });
});

// ============================================
// এরর হ্যান্ডলার
// ============================================
app.use((err, req, res, next) => {
  console.error('Unhandled error:', err);
  res.status(500).json({
    message: 'Internal server error',
    error: process.env.NODE_ENV === 'development' ? err.message : undefined
  });
});

// ============================================
// লোকাল ডেভেলপমেন্টের জন্য সার্ভার স্টার্ট
// ============================================
if (require.main === module) {
  const PORT = process.env.PORT || 3000;
  app.listen(PORT, () => {
    console.log(`\n🚀 Mavro Essence API is running!`);
    console.log(`📡 Local: http://localhost:${PORT}`);
    console.log(`🔑 API Key: ${API_KEY}\n`);
    console.log(`📋 Available endpoints:`);
    console.log(`   GET  /`);
    console.log(`   GET  /health`);
    console.log(`   POST /webhooks`);
    console.log(`   GET  /categories`);
    console.log(`   POST /categories`);
    console.log(`   GET  /products`);
    console.log(`   POST /products`);
    console.log(`   POST /products/:id/variations`);
    console.log(`   DELETE /products/:id`);
    console.log(`   GET  /orders`);
    console.log(`   POST /orders`);
    console.log(`   PUT  /orders/:id`);
    console.log(`   POST /orders/:id/timelines`);
    console.log(`   POST /orders/:id/verify-payment`);
    console.log(`   GET  /stats\n`);
  });
}

// Vercel-এর জন্য এক্সপোর্ট
module.exports = app;
