/**
 * JavaScript for the HTML version of the chat application
 */

// Import CSS
import '../css/chat.css';

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
