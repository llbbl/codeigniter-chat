/**
 * JavaScript for the JSON version of the chat application
 */

// Import CSS
import '../css/chat.css';

// Track current page for lazy loading
let currentPage = 1;
const messagesPerPage = 10;
let hasMoreMessages = true;

$(document).ready(function(){
    loadMsg(currentPage);            
    hideLoading();

    // Add load more button after message window
    $("#messagewindow").after('<div id="load-more-container"><button id="load-more-btn" style="display:none;">Load More Messages</button></div>');

    // Add click handler for load more button
    $("#load-more-btn").click(function() {
        if (hasMoreMessages) {
            currentPage++;
            $(this).text('Loading...');
            loadOlderMessages(currentPage);
        }
    });

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

function addMessages(data, append = false) {
    let messagesAdded = 0;

    if (data.messages && data.messages.length > 0) {
        $.each(data.messages, function(i, val){
            // Escape HTML in the user and message before adding to the DOM
            var escapedUser = $('<div/>').text(val.user).html();
            var escapedMsg = $('<div/>').text(val.msg).html();

            if (append) {
                $("#messagewindow").append("<b>"+escapedUser+"</b>: "+escapedMsg+"<br />");
            } else {
                $("#messagewindow").append("<b>"+escapedUser+"</b>: "+escapedMsg+"<br />");
            }
            messagesAdded++;
        });
    }

    // Check if we have pagination info
    if (data.pagination) {
        hasMoreMessages = data.pagination.hasNext;
    } else {
        hasMoreMessages = false;
    }

    // Show or hide load more button based on whether there are more messages
    if (hasMoreMessages) {
        $("#load-more-btn").show().text('Load More Messages');
    } else {
        $("#load-more-btn").hide();
    }

    return messagesAdded;
}

function loadMsg(page = 1) {
    // Set up AJAX with CSRF token
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $.getJSON(CHAT_ROUTES.jsonBackend + '?page=' + page + '&per_page=' + messagesPerPage, function(data) {
        $("#loading").remove();                
        addMessages(data);
    });
}

function loadOlderMessages(page) {
    $.getJSON(CHAT_ROUTES.jsonBackend + '?page=' + page + '&per_page=' + messagesPerPage, function(data) {
        const messagesAdded = addMessages(data, true);

        if (messagesAdded === 0) {
            $("#load-more-btn").text('No more messages').prop('disabled', true);
        } else {
            $("#load-more-btn").text('Load More Messages');
        }
    }).fail(function() {
        currentPage--; // Revert page increment on failure
        $("#load-more-btn").text('Failed to load. Try again.');
    });
}
