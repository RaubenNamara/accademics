<!DOCTYPE html>
<html>
<head>
    <title>Test Teachers API</title>
</head>
<body>
    <h1>Test Teachers API from Frontend</h1>
    <button onclick="testAPI()">Test API</button>
    <pre id="result"></pre>

    <script>
        async function testAPI() {
            const result = document.getElementById('result');
            try {
                const response = await fetch('http://localhost/accademics/backend/api/teachers.php');
                const data = await response.json();
                result.textContent = JSON.stringify(data, null, 2);
                console.log('API Response:', data);
            } catch (error) {
                result.textContent = 'Error: ' + error.message;
                console.error('API Error:', error);
            }
        }
    </script>
</body>
</html>
