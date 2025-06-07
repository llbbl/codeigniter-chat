/**
 * JavaScript for the JSON version of the chat application
 */

$(document).ready(function(){
    loadMsg();            
    hideLoading();

    $("form#chatform").submit(function(){
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
                    var escapedName = $('<div/>').text(name).html();
                    var escapedMessage = $('<div/>').text(message).html();
                    $("#messagewindow").prepend("<b>"+escapedName+"</b>: "+escapedMessage+"<br />");

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

function addMessages(json) {
    $.each(json, function(i, val){
        // Escape HTML in the user and message before adding to the DOM
        var escapedUser = $('<div/>').text(val.user).html();
        var escapedMsg = $('<div/>').text(val.msg).html();
        $("#messagewindow").append("<b>"+escapedUser+"</b>: "+escapedMsg+"<br />");                
    });
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
    });

    //setTimeout('loadMsg()', 4000);
}