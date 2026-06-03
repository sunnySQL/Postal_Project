<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
    <div class="bg-white rounded-lg shadow-md p-8 max-w-lg w-full">
        <div class="text-center mb-6">
            <div class="bg-red-100 inline-flex p-4 rounded-full mb-4">
                <i class="fas fa-exclamation-triangle text-red-500 text-4xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Unauthorized Access</h1>
            <p class="text-gray-600">You do not have permission to access this resource.</p>
        </div>
        
        <div class="border-t border-gray-200 pt-6">
            <p class="text-gray-700 mb-4">If you believe this is an error, please contact your system administrator.</p>
            
            <div class="flex justify-center space-x-4">
                <a href="index.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                    <i class="fas fa-home mr-2"></i> Go to Homepage
                </a>
                <a href="login.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                    <i class="fas fa-sign-in-alt mr-2"></i> Login Again
                </a>
            </div>
        </div>
    </div>
</body>
</html> 