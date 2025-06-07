<!DOCTYPE html>
<html>
<head>
    <title>CodeIgniter Shoutbox</title>

    <style type="text/css">
        #messagewindow {
            height: 250px;
            border: 1px solid;
            padding: 5px;
            overflow: auto;
        }
        #wrapper {
            margin: auto;
            width: 438px;
        }
        .error {
            color: red;
            font-size: 0.8em;
            margin-top: 5px;
        }
        .error-field {
            border: 1px solid red;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('chatform');
            const nameInput = document.getElementById('name');
            const contentInput = document.getElementById('content');
            const nameError = document.getElementById('name-error');
            const contentError = document.getElementById('content-error');

            form.addEventListener('submit', function(e) {
                let isValid = true;

                // Reset errors
                nameError.textContent = '';
                contentError.textContent = '';
                nameInput.classList.remove('error-field');
                contentInput.classList.remove('error-field');

                // Validate name
                if (!nameInput.value.trim()) {
                    nameError.textContent = 'Name is required';
                    nameInput.classList.add('error-field');
                    isValid = false;
                } else if (nameInput.value.trim().length < 2) {
                    nameError.textContent = 'Name must be at least 2 characters long';
                    nameInput.classList.add('error-field');
                    isValid = false;
                } else if (nameInput.value.trim().length > 50) {
                    nameError.textContent = 'Name cannot exceed 50 characters';
                    nameInput.classList.add('error-field');
                    isValid = false;
                } else if (!/^[a-zA-Z0-9 ]+$/.test(nameInput.value.trim())) {
                    nameError.textContent = 'Name can only contain alphanumeric characters and spaces';
                    nameInput.classList.add('error-field');
                    isValid = false;
                }

                // Validate message
                if (!contentInput.value.trim()) {
                    contentError.textContent = 'Message is required';
                    contentInput.classList.add('error-field');
                    isValid = false;
                } else if (contentInput.value.trim().length > 500) {
                    contentError.textContent = 'Message cannot exceed 500 characters';
                    contentInput.classList.add('error-field');
                    isValid = false;
                }

                if (!isValid) {
                    e.preventDefault();
                }
            });
        });
    </script>
</head>
<body>
    <div id="wrapper">
    <p id="messagewindow">

    <?= esc($html ?? '') ?>

    </p>
    <?php if (session()->has('errors')): ?>
        <div class="error">
            <?php foreach (session('errors') as $error): ?>
                <p><?= esc($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <form id="chatform" action="<?= esc(site_url('chat/update')) ?>" method="post">
    <?= csrf_field() ?>
    <div id="author">
        Name: <input type="text" name="name" id="name" value="<?= esc(old('name')) ?>" />
        <div id="name-error" class="error"></div>
    </div><br />

    <div id="txt">
        Message: <input type="text" name="message" id="content" value="<?= esc(old('message')) ?>" />
        <div id="content-error" class="error"></div>
    </div>

    <br />
    <input type="hidden" name="html_redirect" value="true" />
    <input type="submit" value="ok" /><br />
    </form>
    </div>
</body>
</html>
