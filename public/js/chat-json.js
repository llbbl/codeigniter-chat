/**
 * JavaScript for the JSON version of the chat application
 */

// Debounce function to prevent excessive function calls
function debounce(func, wait) {
    let timeout;
    return function() {
        const context = this;
        const args = arguments;
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            func.apply(context, args);
        }, wait);
    };
}

$(document).ready(function(){
    loadMsg();            
    hideLoading();

    // Create a debounced submit handler to prevent multiple submissions
    const debouncedSubmitHandler = debounce(function(event) {
        // Clear previous errors
        $(".error").text("");
        $("input").removeClass("error-field");

        // Get values
        var name = $("#name").val().trim();
        var message = $("#content").val().trim();
        var isValid = true;

        // Validate name
        if (name === "") {
            $("#name-error").text("Name is required");
            $("#name").addClass("error-field");
            isValid = false;
        } else if (name.length < 2) {
            $("#name-error").text("Name must be at least 2 characters long");
            $("#name").addClass("error-field");
            isValid = false;
        } else if (name.length > 50) {
            $("#name-error").text("Name cannot exceed 50 characters");
            $("#name").addClass("error-field");
            isValid = false;
        } else if (!/^[a-zA-Z0-9 ]+$/.test(name)) {
            $("#name-error").text("Name can only contain alphanumeric characters and spaces");
            $("#name").addClass("error-field");
            isValid = false;
        }

        // Validate message
        if (message === "") {
            $("#content-error").text("Message is required");
            $("#content").addClass("error-field");
            isValid = false;
        } else if (message.length > 500) {
            $("#content-error").text("Message cannot exceed 500 characters");
            $("#content").addClass("error-field");
            isValid = false;
        }

        // If validation passes, submit the form
        if (isValid) {
            showLoading();

            // Add CSRF token to the request
            $.post(CHAT_ROUTES.update, {
                        message: message,
                        name: name,
                        action: "postmsg",
                        [CSRF_TOKEN_NAME]: $('meta[name="csrf-token"]').attr('content')
                    }, function(response) {
                hideLoading();

                if (response && response.success === false) {
                    // Display server-side validation errors
                    if (response.errors.name) {
                        $("#name-error").text(response.errors.name);
                        $("#name").addClass("error-field");
                    }
                    if (response.errors.message) {
                        $("#content-error").text(response.errors.message);
                        $("#content").addClass("error-field");
                    }
                } else {
                    // Success - add message to window
                    // Escape HTML in the name and message before adding to the DOM
                    var escapedName = escapeHTML(name);
                    var escapedMessage = escapeHTML(message);

                    // Create a new message element
                    var messageElement = document.createElement('div');
                    messageElement.innerHTML = "<b>" + escapedName + "</b>: " + escapedMessage;

                    // Prepend to message window (more efficient than string concatenation)
                    $("#messagewindow").prepend(messageElement);

                    // Clear message field and focus
                    $("#content").val("");                    
                    $("#content").focus();
                }
            }, "json").fail(function() {
                hideLoading();
                alert("An error occurred while sending your message. Please try again.");
            });
        }

        return false;
    }, 300); // 300ms debounce time

    // Attach the debounced handler to the form submit event
    $("form#chatform").submit(function(event) {
        debouncedSubmitHandler(event);
        return false;
    });
});

function showLoading(){
    $("#contentLoading").show();
    $("#txt").hide();
    $("#author").hide();
}

function hideLoading(){
    $("#contentLoading").hide();
    $("#txt").show();
    $("#author").show();
}

function escapeHTML(str) {
    // More efficient way to escape HTML
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function addMessages(json) {
    // Create a document fragment to batch DOM operations
    var fragment = document.createDocumentFragment();
    var messageContainer = document.createElement('div');

    $.each(json, function(i, val){
        // Escape HTML in the user and message
        var escapedUser = escapeHTML(val.user);
        var escapedMsg = escapeHTML(val.msg);

        // Create message element
        var messageElement = document.createElement('div');
        messageElement.innerHTML = "<b>" + escapedUser + "</b>: " + escapedMsg;
        messageContainer.appendChild(messageElement);
    });

    // Add all messages to the fragment
    fragment.appendChild(messageContainer);

    // Append the fragment to the DOM (single reflow/repaint)
    $("#messagewindow").append(fragment);
}

function loadMsg() {
    // Set up AJAX with CSRF token
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $.getJSON(CHAT_ROUTES.jsonBackend, function(json) {
        $("#loading").remove();                
        addMessages(json);

        // Schedule next update using requestAnimationFrame for better performance
        // This ensures the browser is ready to perform the next update
        requestAnimationFrame(function() {
            setTimeout(loadMsg, 4000); // 4 second interval
        });
    }).fail(function() {
        // If request fails, try again after a longer delay
        setTimeout(loadMsg, 10000); // 10 second interval on failure
    });
}
