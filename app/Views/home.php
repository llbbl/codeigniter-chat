<!DOCTYPE html>
<html>
<head>
    <title>CodeIgniter Chat - Home</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
        }
        .user-info {
            background-color: #e9f7fe;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .implementations {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .implementation {
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            transition: transform 0.2s;
        }
        .implementation:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        h2 {
            margin-top: 0;
            color: #0066cc;
        }
        a {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 15px;
            background-color: #0066cc;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            transition: background-color 0.2s;
        }
        a:hover {
            background-color: #0052a3;
        }
        .logout {
            float: right;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 0.9em;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="user-info">
            Welcome, <b><?= esc(session()->get('username')) ?></b>! 
            <a href="<?= esc(site_url('auth/logout')) ?>" class="logout">Logout</a>
        </div>
        
        <h1>CodeIgniter Chat Implementations</h1>
        <p>This application demonstrates different ways to implement a chat interface using various technologies. Each implementation serves as a reference for different approaches to building web applications.</p>
        
        <div class="implementations">
            <div class="implementation">
                <h2>XML Version (jQuery)</h2>
                <p>Uses jQuery with AJAX to load and post messages in XML format.</p>
                <p><strong>Technology:</strong> jQuery, AJAX, XML</p>
                <a href="<?= esc(site_url('chat')) ?>">Try XML Version</a>
            </div>
            
            <div class="implementation">
                <h2>JSON Version (jQuery)</h2>
                <p>Similar to XML but uses JSON format for data exchange.</p>
                <p><strong>Technology:</strong> jQuery, AJAX, JSON</p>
                <a href="<?= esc(site_url('chat/json')) ?>">Try JSON Version</a>
            </div>
            
            <div class="implementation">
                <h2>HTML Version</h2>
                <p>Uses traditional form submission with page reloads.</p>
                <p><strong>Technology:</strong> HTML Forms, minimal JS</p>
                <a href="<?= esc(site_url('chat/html')) ?>">Try HTML Version</a>
            </div>
            
            <div class="implementation">
                <h2>Vue.js Version</h2>
                <p>Modern implementation using Vue.js framework.</p>
                <p><strong>Technology:</strong> Vue.js, Fetch API, JSON</p>
                <a href="<?= esc(site_url('chat/vue')) ?>">Try Vue.js Version</a>
            </div>
        </div>
        
        <div class="footer">
            <p>For more information about these implementations, see the <a href="https://github.com/llbbl/codeigniter-chat/blob/main/docs/chat-implementations.md" style="background: none; color: #0066cc; padding: 0;">documentation</a>.</p>
        </div>
    </div>
</body>
</html>