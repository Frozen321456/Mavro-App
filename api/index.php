<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Admin | Mavro Essence</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <style>
        :root { --dark: #111; --bg: #f8f9fa; }
        body { background: var(--bg); font-family: 'Inter', sans-serif; }
        .sidebar { background: #fff; height: 100vh; position: fixed; width: 250px; border-right: 1px solid #ddd; padding: 20px; z-index: 100; overflow-y: auto; }
        .main { margin-left: 250px; padding: 30px; }
        @media (max-width: 768px) { .sidebar { height: auto; width: 100%; position: relative; } .main { margin-left: 0; padding: 15px; } }
        .card-custom { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 25px; border: none; }
        .nav-link { color: #333; padding: 12px; border-radius: 8px; margin-bottom: 5px; cursor: pointer; display: block; text-decoration: none; }
        .nav-link.active { background: #111; color: #fff; }
        #editor { height: 200px; background: white; margin-bottom: 20px; }
        .variation-row { background: #f9f9f9; padding: 15px; border: 1px dashed #ccc; border-radius: 8px; margin-bottom: 10px; }
        .p-img-preview { width: 50px; height: 50px; object-fit: cover; border-radius: 5px; }
    </style>
</head>
<body>

<div class="sidebar">
    <h4 class="mb-4 fw-bold text-center">MAVRO ADMIN</h4>
    <nav class="nav flex-column">
        <a onclick="showSection('dashboard')" class="nav-link active"><i class="fa fa-dashboard me-2"></i> Dashboard</a>
        <a onclick="showSection('products')" class="nav-link"><i class="fa fa-box me-2"></i> Products</a>
        <a onclick="showSection('categories')" class="nav-link"><i class="fa fa-list me-2"></i> Categories</a>
        <a onclick="showSection('orders')" class="nav-link"><i class="fa fa-shopping-bag me-2"></i> Order Management</a>
    </nav>
    <div class="mt-5 p-2 border-top text-center" id="apiStatus">
        <small class="text-muted">API: Checking...</small>
    </div>
</div>

<div class="main">
    <div id="dashboard" class="section">
        <h3 class="fw-bold mb-4">Dashboard</h3>
        <div class="row">
            <div class="col-md-4"><div class="card-custom text-center"><h6>Total Products</h6><h2 id="totalProducts">0</h2></div></div>
            <div class="col-md-4"><div class="card-custom text-center"><h6>New Orders</h6><h2 id="totalOrders">0</h2></div></div>
        </div>
    </div>

    <div id="products" class="section" style="display:none;">
        <div class="card-custom">
            <h5 class="mb-4 fw-bold">Publish Product</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Product Name *</label>
                    <input type="text" id="pName" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">SKU *</label>
                    <input type="text" id="pSku" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Base Price *</label>
                    <input type="number" id="pPrice" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Category *</label>
                    <select id="pCatSel" class="form-select"></select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Main Image URL *</label>
                    <input type="text" id="pImgUrl" class="form-control">
                </div>

                <div class="col-12 mt-2">
                    <label class="form-label small fw-bold">Gallery Image URLs (Optional)</label>
                    <div class="row g-2">
                        <div class="col-md-4"><input type="text" id="gal1" class="form-control" placeholder="Gallery Link 1"></div>
                        <div class="col-md-4"><input type="text" id="gal2" class="form-control" placeholder="Gallery Link 2"></div>
                        <div class="col-md-4"><input type="text" id="gal3" class="form-control" placeholder="Gallery Link 3"></div>
                    </div>
                </div>

                <div class="col-12 mt-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="fw-bold small">Variations (Size/Price)</label>
                        <button type="button" class="btn btn-sm btn-dark" onclick="addVariation()">+ Add Variation</button>
                    </div>
                    <div id="variationContainer"></div>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-bold">Description</label>
                    <div id="editor"></div>
                </div>
                <button id="savePBtn" class="btn btn-dark w-100 py-3 fw-bold">PUBLISH PRODUCT</button>
            </div>
        </div>
        <div class="card-custom">
            <h5>Product List</h5>
            <table class="table align-middle">
                <thead><tr><th>Image</th><th>Name</th><th>SKU</th><th>Price</th><th>Action</th></tr></thead>
                <tbody id="pList"></tbody>
            </table>
        </div>
    </div>

    <div id="categories" class="section" style="display:none;">
        <div class="card-custom">
            <h5>Categories</h5>
            <div class="input-group mb-3">
                <input type="text" id="catName" class="form-control">
                <button id="saveCatBtn" class="btn btn-dark">Add</button>
            </div>
            <div id="catList"></div>
        </div>
    </div>

    <div id="orders" class="section" style="display:none;">
        <div class="card-custom">
            <h5 class="mb-4">Customer Orders</h5>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Order ID</th><th>Customer</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody id="orderList"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.7.1/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.7.1/firebase-firestore-compat.js"></script>

<script>
    const firebaseConfig = {
        apiKey: "AIzaSyAB7dyaJwkadV7asGOhj6TCN5it5pCWg10",
        authDomain: "espera-mavro-6ddc5.firebaseapp.com",
        projectId: "espera-mavro-6ddc5",
        storageBucket: "espera-mavro-6ddc5.appspot.com",
        messagingSenderId: "137893255543",
        appId: "1:137893255543:web:a660bd412eb26ee322a972"
    };
    firebase.initializeApp(firebaseConfig);
    const db = firebase.firestore();
    const quill = new Quill('#editor', { theme: 'snow' });

    const MOVEDROP_API_URL = 'https://mavro-app.vercel.app/api';
    const MOVEDROP_API_KEY = 'MAVRO-ESSENCE-SECURE-KEY-2026';
    let categoryMap = {};

    function showSection(id) {
        document.querySelectorAll('.section').forEach(s => s.style.display = 'none');
        document.getElementById(id).style.display = 'block';
        document.querySelectorAll('.nav-link').forEach(n => n.classList.remove('active'));
        event.currentTarget?.classList.add('active');
    }

    // Variation Logic
    function addVariation() {
        const div = document.createElement('div');
        div.className = 'variation-row row g-2';
        div.innerHTML = `
            <div class="col-md-3"><input type="text" class="form-control v-size" placeholder="6ml"></div>
            <div class="col-md-3"><input type="number" class="form-control v-price" placeholder="Price"></div>
            <div class="col-md-5"><input type="text" class="form-control v-img" placeholder="Var Image Link"></div>
            <div class="col-md-1"><button class="btn btn-danger w-100" onclick="this.parentElement.parentElement.remove()">×</button></div>`;
        document.getElementById('variationContainer').appendChild(div);
    }

    // Load Categories
    db.collection("categories").onSnapshot(snap => {
        const sel = document.getElementById('pCatSel');
        sel.innerHTML = '<option value="">Select Category</option>';
        let i = 1;
        snap.forEach(doc => {
            categoryMap[doc.data().name] = doc.data().id || i++;
            sel.innerHTML += `<option value="${doc.data().name}">${doc.data().name}</option>`;
        });
    });

    // Save Product
    document.getElementById('savePBtn').onclick = async () => {
        const name = document.getElementById('pName').value;
        const sku = document.getElementById('pSku').value;
        const price = document.getElementById('pPrice').value;
        const mainImg = document.getElementById('pImgUrl').value;
        const cat = document.getElementById('pCatSel').value;

        const gallery = [document.getElementById('gal1').value, document.getElementById('gal2').value, document.getElementById('gal3').value].filter(url => url !== "");
        
        const variations = [];
        document.querySelectorAll('.variation-row').forEach(row => {
            const size = row.querySelector('.v-size').value;
            const vPrice = row.querySelector('.v-price').value;
            const vImg = row.querySelector('.v-img').value;
            if(size && vPrice) variations.push({ size, price: parseFloat(vPrice), image: vImg || mainImg });
        });

        if(!name || !sku || !mainImg) return alert("Fill required fields!");

        try {
            const payload = {
                title: name, sku, description: quill.root.innerHTML,
                price: parseFloat(price), images: [{src: mainImg, is_default: true}, ...gallery.map(g => ({src: g, is_default: false}))],
                category_ids: [parseInt(categoryMap[cat]) || 1], channel_ids: [1], variations, status: 'published'
            };

            const res = await fetch(`${MOVEDROP_API_URL}?path=products`, {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-API-KEY': MOVEDROP_API_KEY },
                body: JSON.stringify(payload)
            });

            if(res.ok) {
                await db.collection("products").add({ name, sku, price, image: mainImg, gallery, variations, category: cat, created_at: new Date() });
                alert("Published Successfully!");
                location.reload();
            }
        } catch(e) { alert(e.message); }
    };

    // Load Orders
    db.collection("orders").orderBy("timestamp", "desc").onSnapshot(snap => {
        const list = document.getElementById('orderList');
        list.innerHTML = "";
        document.getElementById('totalOrders').innerText = snap.size;
        snap.forEach(doc => {
            const o = doc.data();
            list.innerHTML += `<tr><td>${doc.id.slice(0,8)}</td><td>${o.customerName}</td><td>৳${o.totalAmount}</td><td><span class="badge bg-warning">${o.status || 'Pending'}</span></td><td><button class="btn btn-sm btn-dark" onclick="syncOrder('${doc.id}')">Sync</button></td></tr>`;
        });
    });

    // Load Products
    db.collection("products").orderBy("created_at", "desc").onSnapshot(snap => {
        const list = document.getElementById('pList');
        list.innerHTML = "";
        document.getElementById('totalProducts').innerText = snap.size;
        snap.forEach(doc => {
            const p = doc.data();
            list.innerHTML += `<tr><td><img src="${p.image}" class="p-img-preview"></td><td>${p.name}</td><td>${p.sku}</td><td>৳${p.price}</td><td><button class="btn btn-sm btn-danger" onclick="db.collection('products').doc('${doc.id}').delete()">×</button></td></tr>`;
        });
    });

    // Health Check
    fetch(`${MOVEDROP_API_URL}?path=health`, { headers: { 'X-API-KEY': MOVEDROP_API_KEY } })
    .then(r => r.ok && (document.getElementById('apiStatus').innerHTML = '<span class="text-success small">● Online</span>'));
</script>
</body>
</html>
