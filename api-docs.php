<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

$auth = new Auth();

$page_title = 'API Documentation - ' . SITE_NAME;
$page_description = 'Developer API documentation for ' . SITE_NAME . '. Integrate our crypto site data into your applications.';
$page_keywords = 'API, documentation, developer, integration, crypto sites, JSON';

$additional_head = '
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
    
    <style>
        .api-hero {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(16, 185, 129, 0.1));
            padding: 4rem 0;
            text-align: center;
        }
        
        .api-content {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .endpoint-card {
            background: rgba(51, 65, 85, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(148, 163, 184, 0.1);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .method-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            margin-right: 1rem;
        }
        
        .method-get { background: #10b981; color: white; }
        .method-post { background: #3b82f6; color: white; }
        
        .code-block {
            background: rgba(15, 23, 42, 0.8);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin: 1rem 0;
            overflow-x: auto;
        }
        
        .parameter-table {
            background: rgba(30, 41, 59, 0.5);
            border-radius: 0.75rem;
            overflow: hidden;
            margin: 1rem 0;
        }
        
        .parameter-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .parameter-table th,
        .parameter-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(148, 163, 184, 0.1);
        }
        
        .parameter-table th {
            background: rgba(15, 23, 42, 0.5);
            color: #f1f5f9;
            font-weight: 600;
        }
        
        .parameter-table td {
            color: #94a3b8;
        }
        
        .response-example {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin: 1rem 0;
        }
    </style>
';

include 'includes/header.php';
?>

<main>
    <!-- Hero Section -->
    <section class="api-hero">
        <div class="container">
            <h1 style="font-size: 3rem; margin-bottom: 1rem;">
                <i class="fas fa-code"></i> API Documentation
            </h1>
            <p style="color: #94a3b8; font-size: 1.125rem;">
                Integrate <?php echo SITE_NAME; ?> data into your applications
            </p>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="api-content">
                <!-- Introduction -->
                <div class="endpoint-card">
                    <h2 style="color: #3b82f6; margin-bottom: 1.5rem;">
                        <i class="fas fa-info-circle"></i> API Overview
                    </h2>
                    <p style="color: #94a3b8;">
                        Our REST API provides access to verified crypto site data, ratings, and reviews. 
                        All responses are in JSON format with consistent error handling.
                    </p>
                    
                    <h4 style="color: #f1f5f9; margin: 1.5rem 0 1rem;">Base URL</h4>
                    <div class="code-block">
                        <code style="color: #60a5fa;"><?php echo SITE_URL; ?>/api/v1/</code>
                    </div>
                    
                    <h4 style="color: #f1f5f9; margin: 1.5rem 0 1rem;">Authentication</h4>
                    <p style="color: #94a3b8;">
                        Most endpoints are public and don't require authentication. Rate limiting applies: 100 requests per hour per IP.
                    </p>
                </div>

                <!-- Get Sites Endpoint -->
                <div class="endpoint-card">
                    <h3 style="color: #10b981;">
                        <span class="method-badge method-get">GET</span>
                        /sites
                    </h3>
                    <p style="color: #94a3b8;">Retrieve a list of verified crypto earning sites with ratings and reviews.</p>
                    
                    <h4 style="color: #f1f5f9; margin: 1.5rem 0 1rem;">Parameters</h4>
                    <div class="parameter-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Parameter</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Default</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>category</td>
                                    <td>string</td>
                                    <td>Filter by category (faucet, url_shortener)</td>
                                    <td>all</td>
                                </tr>
                                <tr>
                                    <td>status</td>
                                    <td>string</td>
                                    <td>Filter by status (paying, scam_reported, scam)</td>
                                    <td>paying</td>
                                </tr>
                                <tr>
                                    <td>limit</td>
                                    <td>integer</td>
                                    <td>Number of results (max 100)</td>
                                    <td>20</td>
                                </tr>
                                <tr>
                                    <td>page</td>
                                    <td>integer</td>
                                    <td>Page number for pagination</td>
                                    <td>1</td>
                                </tr>
                                <tr>
                                    <td>sort</td>
                                    <td>string</td>
                                    <td>Sort order (newest, rating, upvotes)</td>
                                    <td>newest</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <h4 style="color: #f1f5f9; margin: 1.5rem 0 1rem;">Example Request</h4>
                    <div class="code-block">
                        <pre><code class="language-bash">curl -X GET "<?php echo SITE_URL; ?>/api/v1/sites?category=faucet&status=paying&limit=10"</code></pre>
                    </div>
                    
                    <h4 style="color: #f1f5f9; margin: 1.5rem 0 1rem;">Example Response</h4>
                    <div class="response-example">
                        <pre><code class="language-json">{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Example Faucet",
      "url": "https://example-faucet.com",
      "category": "faucet",
      "status": "paying",
      "description": "High-paying Bitcoin faucet",
      "supported_coins": "BTC, ETH, LTC",
      "average_rating": 4.5,
      "review_count": 25,
      "total_upvotes": 45,
      "total_downvotes": 3,
      "views": 1250,
      "created_at": "2024-01-15T10:30:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "total_pages": 5,
    "total_results": 47,
    "per_page": 10
  }
}</code></pre>
                    </div>
                </div>

                <!-- Get Site Details -->
                <div class="endpoint-card">
                    <h3 style="color: #10b981;">
                        <span class="method-badge method-get">GET</span>
                        /sites/{id}
                    </h3>
                    <p style="color: #94a3b8;">Get detailed information about a specific site including reviews.</p>
                    
                    <h4 style="color: #f1f5f9; margin: 1.5rem 0 1rem;">Example Request</h4>
                    <div class="code-block">
                        <pre><code class="language-bash">curl -X GET "<?php echo SITE_URL; ?>/api/v1/sites/1"</code></pre>
                    </div>
                </div>

                <!-- Widget Endpoint -->
                <div class="endpoint-card">
                    <h3 style="color: #10b981;">
                        <span class="method-badge method-get">GET</span>
                        /widget
                    </h3>
                    <p style="color: #94a3b8;">Generate embeddable widgets for external websites.</p>
                    
                    <h4 style="color: #f1f5f9; margin: 1.5rem 0 1rem;">Parameters</h4>
                    <div class="parameter-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Parameter</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Required</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>site</td>
                                    <td>integer</td>
                                    <td>Site ID to display</td>
                                    <td>Yes</td>
                                </tr>
                                <tr>
                                    <td>type</td>
                                    <td>string</td>
                                    <td>Widget type (card, banner, compact)</td>
                                    <td>No</td>
                                </tr>
                                <tr>
                                    <td>theme</td>
                                    <td>string</td>
                                    <td>Color theme (dark, light)</td>
                                    <td>No</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Error Handling -->
                <div class="endpoint-card">
                    <h3 style="color: #ef4444;">
                        <i class="fas fa-exclamation-triangle"></i> Error Handling
                    </h3>
                    <p style="color: #94a3b8;">All API responses include a success field and appropriate HTTP status codes.</p>
                    
                    <h4 style="color: #f1f5f9; margin: 1.5rem 0 1rem;">Error Response Format</h4>
                    <div class="code-block">
                        <pre><code class="language-json">{
  "success": false,
  "error": {
    "code": "SITE_NOT_FOUND",
    "message": "The requested site was not found or is not approved"
  }
}</code></pre>
                    </div>
                    
                    <h4 style="color: #f1f5f9; margin: 1.5rem 0 1rem;">HTTP Status Codes</h4>
                    <ul style="color: #94a3b8;">
                        <li><strong>200:</strong> Success</li>
                        <li><strong>400:</strong> Bad Request - Invalid parameters</li>
                        <li><strong>404:</strong> Not Found - Resource doesn't exist</li>
                        <li><strong>429:</strong> Too Many Requests - Rate limit exceeded</li>
                        <li><strong>500:</strong> Internal Server Error</li>
                    </ul>
                </div>

                <!-- Rate Limiting -->
                <div class="endpoint-card">
                    <h3 style="color: #f59e0b;">
                        <i class="fas fa-gauge-high"></i> Rate Limiting
                    </h3>
                    <p style="color: #94a3b8;">
                        To ensure fair usage, we implement rate limiting on our API endpoints.
                    </p>
                    <ul style="color: #94a3b8;">
                        <li><strong>Public Endpoints:</strong> 100 requests per hour per IP</li>
                        <li><strong>Widget Endpoint:</strong> 1000 requests per hour per IP</li>
                        <li><strong>Headers:</strong> Rate limit info included in response headers</li>
                    </ul>
                </div>

                <!-- Contact for API -->
                <div class="card text-center mt-5">
                    <h3>Need API Access or Higher Limits?</h3>
                    <p style="color: #94a3b8;">
                        Contact us for enterprise API access, higher rate limits, or custom integrations.
                    </p>
                    <div class="flex gap-3 justify-center">
                        <a href="contact.php" class="btn btn-primary">
                            <i class="fas fa-envelope"></i> Contact Us
                        </a>
                        <a href="widget.php?site=1" class="btn btn-secondary" target="_blank">
                            <i class="fas fa-code"></i> Try Widget
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
