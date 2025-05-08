<!DOCTYPE html>
<html>
<head>
    <title>Counselor Help</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #eef3f7;
            padding: 30px;
        }
        .faq-container {
            max-width: 800px;
            margin: auto;
        }
        .question {
            background: #ffffff;
            border: 1px solid #ccc;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .answer {
            display: none;
            background: #e8f0fe;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin-top: 5px;
            border-radius: 4px;
        }
        .answer ul {
            margin: 0;
            padding-left: 20px;
        }
    </style>
</head>
<body>

<div class="faq-container">
    <h2>Student Help Center</h2>

    <div class="question" onclick="toggleAnswer(0)">
        1. How do I login into the system?
        <div class="answer">
            <ul>
                <li>Login to the student portal.</li>
                <li>If a new student you have to wait for admin approval" tab.</li>
                
                
            </ul>
        </div>
    </div>

    <div class="question" onclick="toggleAnswer(1)">
        2. How do I Upload My project?
        <div class="answer">
            <ul>
                <li>Login to your portal.</li>
                <li>Navigate to the dashboard section.</li>
                <li>Under the share files there is an upload section and select the supervisor to share with.</li>
                
                <li>Click upload</li>
            </ul>
        </div>
    </div>

    <div class="question" onclick="toggleAnswer(2)">
        3. How do I respond to messages from supervisor?
        <div class="answer">
            <ul>
                <li>Go to the messages tab after login.</li>
                <li>Unread messages are shown with notification icons.</li>
                <li>Click on a message to view it.</li>
                <li>Click "Reply" to send your response.</li>
                <li>All replies are recorded in message table.</li>
            </ul>
        </div>
    </div>

    <div class="question" onclick="toggleAnswer(3)">
        4. How do I receive supervisor comments?
        <div class="answer">
            <ul>
                <li>Go to the student dashboard .</li>
                <li>From the sidebar you will supervisor comments.</li>
                <li>Click the tab supervisor comment.</li>
                <li>All comments will be listed with the dates they were sent</li>
                
            </ul>
        </div>
    </div>

    <div class="question" onclick="toggleAnswer(4)">
        5. How do I receive projects corrections?
        <div class="answer">
            <ul>
                <li>Login to the student portal.</li>
                <li>Click the files from the supervisor tab</li>
                <li>check the files latest from the supervisor.</li>
                <li>Click download.</li>
            </ul>
        </div>
    </div>

</div>

<script>
    function toggleAnswer(index) {
        const answers = document.querySelectorAll('.answer');
        answers[index].style.display = answers[index].style.display === 'block' ? 'none' : 'block';
    }
</script>

</body>
</html>
