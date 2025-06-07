/**
 * JavaScript for the XML version of the chat application
 */

// Import CSS
import '../css/chat.css';

$(document).ready(function(){
    loadMsg();            
    hideLoading();

    $("form#chatform").submit(function(){
        // Clear previous errors
        $(".error").text("");
        $("input").removeClass("error-field");

        // Get values
        var message = $("#content").val().trim();
        var isValid = true;

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
                        action: "postmsg",
                        [CSRF_TOKEN_NAME]: $('meta[name="csrf-token"]').attr('content')
                    }, function(response) {
                hideLoading();

                if (response && response.success === false) {
                    // Display server-side validation errors
                    if (response.errors.message) {
                        $("#content-error").text(response.errors.message);
                        $("#content").addClass("error-field");
                    }
                } else {
                    // Success - add message to window
                    // Escape HTML in the message before adding to the DOM
                    var escapedMessage = $('<div/>').text(message).html();
                    $("#messagewindow").prepend("<b>" + CURRENT_USERNAME + "</b>: "+escapedMessage+"<br />");

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

function addMessages(xml) {
    $(xml).find('message').each(function() {
        var author = $(this).find("author").text();
        var msg = $(this).find("text").text();

        // Escape HTML in the author and message before adding to the DOM
        var escapedAuthor = $('<div/>').text(author).html();
        var escapedMsg = $('<div/>').text(msg).html();

        $("#messagewindow").append("<b>"+escapedAuthor+"</b>: "+escapedMsg+"<br />");
    });
}

function loadMsg() {
    // Set up AJAX with CSRF token
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $.get(CHAT_ROUTES.backend, function(xml) {
        $("#loading").remove();                
        addMessages(xml);
    });

    //setTimeout('loadMsg()', 4000);
}
