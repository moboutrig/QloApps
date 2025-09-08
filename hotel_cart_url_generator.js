// Hotel Cart URL Generator for n8n
// This code can be used in a Function node or Code node in n8n

// Input parameters - these would come from previous nodes or webhook parameters
const inputData = $input.all();

// Default configuration - can be overridden by input data
const config = {
  baseUrl: "https://parksuiteshotel.aiartexpo.art/index.php",
  token: "b99d700a1519be7LHFLKKJ794f59ca096b213cc",
  actionType: "add", // "add" or "delete"
  ajaxMode: false,
  // Support for multiple room configurations with room-specific services
  roomConfigs: [] // Array of {id_product, dateFrom, dateTo, occupancy, serviceProducts}
};

// Function to merge input data with default config
function mergeConfig(inputData) {
  const merged = { ...config };

  if (inputData.length > 0) {
    const input = inputData[0].json;

    // If the input is an array, treat it as roomConfigs
    if (Array.isArray(input)) {
      merged.roomConfigs = input;
    } else if (input && typeof input === 'object') {
      // Otherwise, merge the input object with the default config
      Object.assign(merged, input);
    }
  }

  return merged;
}

// Function to validate room occupancy data
function validateRoomData(rooms) {
  if (!rooms || rooms.length === 0) {
    return [{
      adults: 2,
      children: 0,
      child_ages: []
    }];
  }

  return rooms.map(room => ({
    adults: Math.max(1, parseInt(room.adults) || 1),
    children: Math.max(0, parseInt(room.children) || 0),
    child_ages: room.child_ages || []
  }));
}

// Function to validate service data
function validateServiceData(services) {
  if (!services || services.length === 0) {
    return [];
  }

  return services.filter(service => service.id_product && service.id_product > 0)
    .map(service => ({
      id_product: parseInt(service.id_product),
      quantity: Math.max(1, parseInt(service.quantity) || 1)
    }));
}

// Function to generate a hotel cart URL from a config object
function generateHotelCartURL(config) {
  const {
    baseUrl,
    token,
    actionType,
    ajaxMode,
    productId,
    id_product,
    dateFrom,
    dateTo,
    rooms,
    occupancy,
    services,
    serviceProducts,
  } = config;

  const finalProductId = productId || id_product;
  const finalOccupancy = rooms || occupancy;
  const finalServices = services || serviceProducts;

  // Build base URL
  let url = `${baseUrl}?controller=cart&${actionType}=1&id_product=${finalProductId}&token=${token}`;

  // Add AJAX parameter if enabled
  if (ajaxMode) {
    url += '&ajax=1';
  }

  // For "add" actions, include dates, occupancy, and services
  if (actionType === 'add') {
    url += `&dateFrom=${dateFrom}&dateTo=${dateTo}`;

    const validatedOccupancy = validateRoomData(finalOccupancy);
    const occupancyEncoded = encodeURIComponent(JSON.stringify(validatedOccupancy));
    url += `&occupancy=${occupancyEncoded}`;

    const validatedServices = validateServiceData(finalServices);
    if (validatedServices.length > 0) {
      const serviceProductsEncoded = encodeURIComponent(JSON.stringify(validatedServices));
      url += `&serviceProducts=${serviceProductsEncoded}`;
    }
  }

  return url;
}

// Function to generate multiple URLs from room configurations
function generateMultipleRoomURLs(roomConfigs, baseConfig) {
  return roomConfigs.map((roomConfig, index) => {
    const combinedConfig = { ...baseConfig, ...roomConfig };
    const url = generateHotelCartURL(combinedConfig);

    const occupancyDetails = roomConfig.occupancy.map(o => `${o.adults} adults, ${o.children} children`).join('; ');
    const description = `Room Type ${roomConfig.id_product} - ${roomConfig.occupancy.length} room(s) (${occupancyDetails})`;

    return {
      step: index + 1,
      roomTypeId: roomConfig.id_product,
      dateFrom: roomConfig.dateFrom,
      dateTo: roomConfig.dateTo,
      occupancy: roomConfig.occupancy,
      serviceProducts: roomConfig.serviceProducts || [],
      url: url,
      description: description
    };
  });
}

// Function to generate multiple URLs for different room types
function generateRoomTypeURLs(roomTypeConfigs, baseConfig) {
  return roomTypeConfigs.map((roomTypeConfig, index) => {
    const config = {
      ...baseConfig,
      productId: roomTypeConfig.roomTypeId,
      rooms: roomTypeConfig.rooms,
      services: roomTypeConfig.services || [],
      // Assuming dates are part of the baseConfig or roomTypeConfig
      dateFrom: roomTypeConfig.dateFrom || baseConfig.dateFrom,
      dateTo: roomTypeConfig.dateTo || baseConfig.dateTo,
    };

    const url = generateHotelCartURL(config);

    return {
      step: index + 1,
      roomTypeId: roomTypeConfig.roomTypeId,
      roomCount: roomTypeConfig.rooms.length,
      url: url,
      description: roomTypeConfig.description || `Room Type ${roomTypeConfig.roomTypeId}`
    };
  });
}

// Function to decode URL parameters for debugging
function decodeURLParameters(url) {
  try {
    const urlObj = new URL(url);
    const params = {};

    for (let [key, value] of urlObj.searchParams) {
      if (key === 'occupancy' || key === 'serviceProducts') {
        try {
          params[key] = JSON.parse(decodeURIComponent(value));
        } catch (e) {
          params[key] = value;
        }
      } else {
        params[key] = value;
      }
    }

    return params;
  } catch (e) {
    return { error: 'Invalid URL format' };
  }
}

// Preset configurations (can be used for testing or quick generation)
const presets = {
  couple: {
    roomConfigs: [{
      id_product: 2,
      dateFrom: "2025-12-01",
      dateTo: "2025-12-05",
      occupancy: [{ adults: 2, children: 0, child_ages: [] }],
      serviceProducts: [
        { id_product: 12, quantity: 2 },
        { id_product: 15, quantity: 1 }
      ]
    }]
  },
  family: {
    roomConfigs: [{
      id_product: 4,
      dateFrom: "2025-12-15",
      dateTo: "2025-12-20",
      occupancy: [
        { adults: 2, children: 2, child_ages: [8, 12] },
        { adults: 2, children: 0, child_ages: [] }
      ],
      serviceProducts: []
    }]
  }
};

// Main execution logic
try {
  // Merge input configuration
  const finalConfig = mergeConfig(inputData);

  // Handle room configurations array
  if (finalConfig.roomConfigs && finalConfig.roomConfigs.length > 0) {
    const multiRoomURLs = generateMultipleRoomURLs(finalConfig.roomConfigs, finalConfig);

    return [{
      json: {
        success: true,
        type: 'multi-room-booking',
        urls: multiRoomURLs,
        totalSteps: multiRoomURLs.length,
        instructions: "Execute URLs in the order provided to book multiple rooms/services",
        config: finalConfig
      }
    }];
  }

  // If no room configurations provided, return error
  return [{
    json: {
      success: false,
      error: "No room configurations provided. Please provide roomConfigs array.",
      expectedFormat: "Array of {id_product, dateFrom, dateTo, occupancy, serviceProducts}",
      type: 'validation-error'
    }
  }];

} catch (error) {
  return [{
    json: {
      success: false,
      error: error.message,
      type: 'error'
    }
  }];
}

// Helper functions for specific use cases (can be called separately)

// Function to create a simple booking URL (minimal parameters)
function createSimpleBookingURL(roomTypeId, checkIn, checkOut, adults = 2, children = 0) {
  const config = {
    baseUrl: "https://parksuiteshotel.aiartexpo.art/index.php",
    token: "b99d700a1519be7LHFLKKJ794f59ca096b213cc",
    productId: roomTypeId,
    dateFrom: checkIn,
    dateTo: checkOut,
    actionType: "add",
    rooms: [{ adults, children, child_ages: [] }],
    services: []
  };

  return generateHotelCartURL(config);
}

// Function to create a delete URL
function createDeleteURL(roomTypeId) {
  const config = {
    baseUrl: "https://parksuiteshotel.aiartexpo.art/index.php",
    token: "b99d700a1519be7LHFLKKJ794f59ca096b213cc",
    productId: roomTypeId,
    actionType: "delete",
    ajaxMode: true
  };

  return generateHotelCartURL(config);
}

// Export functions for use in other nodes (if needed)
// In n8n, you might want to store these in a global variable
// $node.context().global.hotelCartFunctions = {
//   generateHotelCartURL,
//   createSimpleBookingURL,
//   createDeleteURL,
//   generateRoomTypeURLs,
//   presets
// };

/*
TEST INPUTS FOR N8N:

1. Basic Test - Two different room types with room-specific services:
*/
const testInput1 = [
  {
    "id_product": 2,
    "dateFrom": "2025-12-01",
    "dateTo": "2025-12-05",
    "occupancy": [{"adults": 2, "children": 0, "child_ages": []}],
    "serviceProducts": [
      {"id_product": 15, "quantity": 2}, // Breakfast for 2 people
      {"id_product": 20, "quantity": 1}  // Spa package
    ]
  },
  {
    "id_product": 1,
    "dateFrom": "2025-12-01",
    "dateTo": "2025-12-05",
    "occupancy": [{"adults": 1, "children": 0, "child_ages": []}],
    "serviceProducts": [
      {"id_product": 15, "quantity": 1}, // Breakfast for 1 person
      {"id_product": 25, "quantity": 1}  // Airport transfer
    ]
  }
];

/*
2. Family Booking - Multiple rooms with children and specific services:
*/
const testInput2 = [
  {
    "id_product": 4,
    "dateFrom": "2025-12-15",
    "dateTo": "2025-12-20",
    "occupancy": [
      {"adults": 2, "children": 2, "child_ages": [8, 12]}
    ],
    "serviceProducts": [
      {"id_product": 15, "quantity": 4}, // Breakfast for family
      {"id_product": 30, "quantity": 1}, // Kids club access
      {"id_product": 35, "quantity": 2}  // Extra beds
    ]
  },
  {
    "id_product": 2,
    "dateFrom": "2025-12-15",
    "dateTo": "2025-12-20",
    "occupancy": [
      {"adults": 2, "children": 0, "child_ages": []}
    ],
    "serviceProducts": [
      {"id_product": 15, "quantity": 2}, // Breakfast for grandparents
      {"id_product": 40, "quantity": 1}  // Senior discount package
    ]
  }
];

/*
3. Business Group Booking - Same room type, different services:
*/
const testInput3 = [
  {
    "id_product": 3,
    "dateFrom": "2025-11-10",
    "dateTo": "2025-11-12",
    "occupancy": [{"adults": 1, "children": 0, "child_ages": []}],
    "serviceProducts": [
      {"id_product": 50, "quantity": 1}, // Business center access
      {"id_product": 55, "quantity": 1}  // Meeting room (4 hours)
    ]
  },
  {
    "id_product": 3,
    "dateFrom": "2025-11-10",
    "dateTo": "2025-11-12",
    "occupancy": [{"adults": 1, "children": 0, "child_ages": []}],
    "serviceProducts": [
      {"id_product": 50, "quantity": 1}, // Business center access
      {"id_product": 60, "quantity": 1}  // Laundry service
    ]
  },
  {
    "id_product": 3,
    "dateFrom": "2025-11-10",
    "dateTo": "2025-11-12",
    "occupancy": [{"adults": 1, "children": 0, "child_ages": []}],
    "serviceProducts": [
      {"id_product": 50, "quantity": 1}, // Business center access
      {"id_product": 65, "quantity": 1}  // Airport pickup
    ]
  }
];

/*
4. Luxury Booking - Multiple occupancy in same room with premium services:
*/
const testInput4 = [
  {
    "id_product": 5,
    "dateFrom": "2025-12-31",
    "dateTo": "2026-01-02",
    "occupancy": [
      {"adults": 3, "children": 1, "child_ages": [5]},
      {"adults": 2, "children": 0, "child_ages": []}
    ],
    "serviceProducts": [
      {"id_product": 70, "quantity": 5}, // Premium breakfast
      {"id_product": 75, "quantity": 1}, // New Year's Eve package
      {"id_product": 80, "quantity": 2}, // Champagne service
      {"id_product": 85, "quantity": 1}  // Private butler
    ]
  }
];

/*
5. Simple Test - No services:
*/
const testInput5 = [
  {
    "id_product": 1,
    "dateFrom": "2025-10-01",
    "dateTo": "2025-10-03",
    "occupancy": [{"adults": 2, "children": 0, "child_ages": []}],
    "serviceProducts": []
  }
];

/*
6. DELETE Operation Test:
*/
const testInputDelete = {
  "actionType": "delete",
  "ajaxMode": true,
  "roomConfigs": [
    {
      "id_product": 2,
      "serviceProducts": []
    }
  ]
};

/*
HOW TO USE THESE TESTS IN N8N:

Method 1 - Direct Array Input:
In your Function node, replace the input with any of the testInput arrays above.

Method 2 - Webhook/HTTP Request format:
POST to your n8n webhook with this structure:
{
  "roomConfigs": testInput1
}

Method 3 - Testing individual scenarios:
For testInput1: Copy the array and use as direct input
For testInput2: Family booking scenario
For testInput3: Business group with same room type
For testInput4: Complex luxury booking
For testInput5: Simple booking without services
For testInputDelete: Testing delete functionality

EXPECTED OUTPUTS:

Each test will return:
{
  "success": true,
  "type": "multi-room-booking",
  "urls": [
    {
      "step": 1,
      "roomTypeId": 2,
      "dateFrom": "2025-12-01",
      "dateTo": "2025-12-05",
      "occupancy": [...],
      "serviceProducts": [...],
      "url": "https://parksuiteshotel.aiartexpo.art/index.php?controller=cart&add=1&id_product=2&token=...",
      "description": "Room Type 2 - 1 room(s)"
    },
    // ... more URLs for each room configuration
  ],
  "totalSteps": 2,
  "instructions": "Execute URLs in the order provided to book multiple rooms/services"
}

WORKFLOW EXECUTION:
1. Use Split in Batches node to process URLs one by one
2. HTTP Request node to execute each URL
3. Wait node between requests (optional)
4. Merge node to collect all responses
*/
