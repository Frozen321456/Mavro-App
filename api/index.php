// Save Product
document.getElementById('savePBtn').onclick = async () => {
    const name = document.getElementById('pName').value;
    const sku = document.getElementById('pSku').value;
    const price = document.getElementById('pPrice').value;
    const category = document.getElementById('pCatSel').value;
    const tags = document.getElementById('pTags').value.split(',').map(t => t.trim()).filter(t => t);
    const mainImg = document.getElementById('pImg').files[0];

    if (!name || !sku || !price || !mainImg) {
        alert("Please fill all required fields and select main image");
        return;
    }

    document.getElementById('savePBtn').innerHTML = '<i class="fa fa-spinner fa-spin"></i> Publishing...';
    document.getElementById('savePBtn').disabled = true;

    try {
        // Process main image
        const mainBase64 = await toBase64(mainImg);
        const mainCompressed = await compressImage(mainBase64, 500);
        
        // Upload main image to Storage
        const timestamp = Date.now();
        const mainImageUrl = await uploadImageToStorage(
            mainCompressed, 
            `products/${sku}_${timestamp}/main.jpg`
        );
        
        // Process gallery images
        const galleryUrls = [];
        for (let i = 0; i < galleryArr.length; i++) {
            const url = await uploadImageToStorage(
                galleryArr[i],
                `products/${sku}_${timestamp}/gallery-${i}.jpg`
            );
            galleryUrls.push(url);
        }
        
        const allImages = [mainImageUrl, ...galleryUrls];

        // Collect variations
        const variationRows = document.querySelectorAll('[id^="variation-"]');
        const variations = [];
        variationRows.forEach(row => {
            const rowId = row.id.replace('variation-', '');
            const varSku = document.getElementById(`var-sku-${rowId}`)?.value;
            const varPrice = document.getElementById(`var-price-${rowId}`)?.value;
            const varSale = document.getElementById(`var-sale-${rowId}`)?.value;
            const varStock = document.getElementById(`var-stock-${rowId}`)?.value;
            const varColor = document.getElementById(`var-color-${rowId}`)?.value;
            const varSize = document.getElementById(`var-size-${rowId}`)?.value;
            
            if (varSku && varPrice) {
                const properties = [];
                if (varColor) properties.push({ name: "Color", value: varColor });
                if (varSize) properties.push({ name: "Size", value: varSize });
                
                variations.push({
                    sku: varSku,
                    regular_price: parseFloat(varPrice),
                    sale_price: varSale ? parseFloat(varSale) : null,
                    stock_quantity: parseInt(varStock) || 0,
                    image: mainImageUrl,
                    properties: properties
                });
            }
        });

        // Get category ID
        let categoryId = null;
        if (category) {
            const catSnapshot = await db.collection("categories")
                .where("name", "==", category)
                .get();
            if (!catSnapshot.empty) {
                categoryId = catSnapshot.docs[0].id;
            }
        }

        // Prepare images array for MoveDrop
        const moveDropImages = allImages.map((src, index) => ({
            src: src,
            is_default: index === 0
        }));

        // FIRST: Create the main product without variations
        const productResponse = await fetch(`${MOVEDROP_API_URL}?path=products`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-API-KEY': MOVEDROP_API_KEY
            },
            body: JSON.stringify({
                title: name,
                sku: sku,
                description: quill.root.innerHTML,
                images: moveDropImages,
                category_ids: categoryId ? [parseInt(categoryId) || 1] : [],
                tags: tags,
                properties: [] // Don't send properties yet
            })
        });

        const productResult = await productResponse.json();
        console.log("MoveDrop product response:", productResult);

        // SECOND: If product created successfully and has variations, add them separately
        if (variations.length > 0 && productResult.data && productResult.data.id) {
            const variationsResponse = await fetch(`${MOVEDROP_API_URL}?path=products/${productResult.data.id}/variations`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-API-KEY': MOVEDROP_API_KEY
                },
                body: JSON.stringify({ variations: variations })
            });
            
            const variationsResult = await variationsResponse.json();
            console.log("MoveDrop variations response:", variationsResult);
        }

        // Save to Firebase Firestore with all data
        const productData = {
            name: name,
            sku: sku,
            price: parseFloat(price),
            category: category,
            category_id: categoryId,
            tags: tags,
            description: quill.root.innerHTML,
            image: mainImageUrl,
            images: allImages,
            variations: variations,
            created_at: firebase.firestore.FieldValue.serverTimestamp(),
            updated_at: firebase.firestore.FieldValue.serverTimestamp()
        };

        const docRef = await db.collection("products").add(productData);
        console.log("Product saved to Firestore with ID:", docRef.id);

        alert("Product published successfully!");
        
        // Clear form
        document.getElementById('pName').value = '';
        document.getElementById('pSku').value = '';
        document.getElementById('pPrice').value = '';
        document.getElementById('pTags').value = '';
        document.getElementById('pImg').value = '';
        galleryArr = [];
        renderGallery();
        document.getElementById('variationsContainer').innerHTML = '';
        quill.setContents([]);

    } catch (error) {
        alert("Error: " + error.message);
        console.error("Save error:", error);
    } finally {
        document.getElementById('savePBtn').innerHTML = '<i class="fa fa-save me-2"></i> PUBLISH PRODUCT';
        document.getElementById('savePBtn').disabled = false;
    }
};
