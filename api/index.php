<?php
/**
 * MoveDrop Custom Channel - Fixed Category IDs
 * API Key: MAVRO-ESSENCE-SECURE-KEY-2026
 */

// [Previous code remains the same until the products case]

    // Products Endpoints
    case 'products':
        $productId = $pathParts[1] ?? null;
        $subAction = $pathParts[2] ?? null;

        if ($method === 'GET') {
            $products = getCollection('products');
            $formatted = [];
            
            foreach ($products as $id => $prod) {
                $formatted[] = [
                    "id" => $id,
                    "title" => $prod['title'] ?? $prod['name'] ?? '',
                    "sku" => $prod['sku'] ?? '',
                    "tags" => $prod['tags'] ?? [],
                    "created_at" => $prod['created_at'] ?? date('Y-m-d H:i:s'),
                    "updated_at" => $prod['updated_at'] ?? date('Y-m-d H:i:s')
                ];
            }
            
            echo json_encode(paginate($formatted, $page, $perPage));
            
        } elseif ($method === 'POST') {
            if ($productId && $subAction === 'variations') {
                // Add variations to existing product
                $variations = $inputData['variations'] ?? [];
                
                // Get existing product
                $response = firestoreRequest('products', 'GET', $productId);
                if (!$response || isset($response['error'])) {
                    http_response_code(404);
                    echo json_encode(["message" => "Product not found"]);
                    break;
                }
                
                $product = parseFirestoreResponse($response);
                $product['variations'] = $variations;
                $product['updated_at'] = date('Y-m-d H:i:s');
                
                // Update product
                firestoreRequest('products', 'PATCH', $productId, $product);
                
                $results = [];
                foreach ($variations as $index => $var) {
                    $results[] = [
                        "id" => $index + 1,
                        "sku" => $var['sku'] ?? ''
                    ];
                }
                
                echo json_encode([
                    "message" => "Variations Created",
                    "data" => $results
                ]);
                
            } else {
                // Create new product
                $title = $inputData['title'] ?? '';
                $sku = $inputData['sku'] ?? '';
                $category_ids = $inputData['category_ids'] ?? [];
                
                if (!$title || !$sku) {
                    http_response_code(422);
                    echo json_encode(["message" => "Title and SKU required"]);
                    break;
                }
                
                // IMPORTANT: Ensure category_ids is an array with at least one valid ID
                if (empty($category_ids)) {
                    // Try to get or create a default category
                    $defaultCategoryId = getOrCreateDefaultCategory();
                    if ($defaultCategoryId) {
                        $category_ids = [$defaultCategoryId];
                    } else {
                        // If all else fails, use a hardcoded ID
                        $category_ids = [1];
                    }
                }
                
                $data = [
                    'title' => $title,
                    'sku' => $sku,
                    'description' => $inputData['description'] ?? '',
                    'images' => $inputData['images'] ?? [],
                    'category_ids' => $category_ids,
                    'tags' => $inputData['tags'] ?? [],
                    'properties' => $inputData['properties'] ?? [],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $response = firestoreRequest('products', 'POST', null, $data);
                
                if ($response && isset($response['name'])) {
                    $id = basename($response['name']);
                    http_response_code(201);
                    echo json_encode([
                        "message" => "Product Created",
                        "data" => [
                            "id" => $id,
                            "title" => $title,
                            "sku" => $sku,
                            "tags" => $inputData['tags'] ?? [],
                            "created_at" => date('Y-m-d H:i:s'),
                            "updated_at" => date('Y-m-d H:i:s')
                        ]
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode(["message" => "Failed to create product"]);
                }
            }
            
        } elseif ($method === 'DELETE' && $productId) {
            firestoreRequest('products', 'DELETE', $productId);
            echo json_encode(["message" => "Product Deleted"]);
        }
        break;
        
    // Categories Endpoints
    case 'categories':
        if ($method === 'GET') {
            $categories = getCollection('categories');
            $formatted = [];
            
            foreach ($categories as $id => $cat) {
                $formatted[] = [
                    "id" => $id,
                    "name" => $cat['name'] ?? '',
                    "slug" => $cat['slug'] ?? generateSlug($cat['name'] ?? ''),
                    "created_at" => $cat['created_at'] ?? date('Y-m-d H:i:s')
                ];
            }
            
            echo json_encode(paginate($formatted, $page, $perPage));
            
        } elseif ($method === 'POST') {
            $name = $inputData['name'] ?? '';
            if (!$name) {
                http_response_code(422);
                echo json_encode(["message" => "Category name required"]);
                break;
            }
            
            $data = [
                'name' => $name,
                'slug' => generateSlug($name),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $response = firestoreRequest('categories', 'POST', null, $data);
            
            if ($response && isset($response['name'])) {
                $id = basename($response['name']);
                http_response_code(201);
                echo json_encode([
                    "data" => [
                        "id" => $id,
                        "name" => $name,
                        "slug" => generateSlug($name),
                        "created_at" => date('Y-m-d H:i:s')
                    ]
                ]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Failed to create category"]);
            }
        }
        break;

// Add this helper function
function getOrCreateDefaultCategory() {
    // Try to get existing categories
    $categories = getCollection('categories');
    
    if (!empty($categories)) {
        // Return the first category ID
        return array_key_first($categories);
    }
    
    // Create a default category
    $defaultData = [
        'name' => 'General',
        'slug' => 'general',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $response = firestoreRequest('categories', 'POST', null, $defaultData);
    
    if ($response && isset($response['name'])) {
        return basename($response['name']);
    }
    
    return 1; // Fallback to ID 1
}

// [Rest of the code remains the same]
