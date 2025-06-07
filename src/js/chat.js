/**
 * JavaScript for the XML version of the chat application
 */

// Import jQuery and CSS
import $ from 'jquery';
import '../css/chat.css';

// Make jQuery available globally
window.$ = window.jQuery = $;

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

function addMessages(xml, append = false) {
    let messagesAdded = 0;

    $(xml).find('message').each(function() {
        var author = $(this).find("author").text();
        var msg = $(this).find("text").text();

        // Escape HTML in the author and message before adding to the DOM
        var escapedAuthor = $('<div/>').text(author).html();
        var escapedMsg = $('<div/>').text(msg).html();

        if (append) {
            $("#messagewindow").append("<b>"+escapedAuthor+"</b>: "+escapedMsg+"<br />");
        } else {
            $("#messagewindow").append("<b>"+escapedAuthor+"</b>: "+escapedMsg+"<br />");
        }
        messagesAdded++;
    });

    // Check if we have pagination info
    const totalPages = parseInt($(xml).find('pagination totalPages').text()) || 0;
    hasMoreMessages = currentPage < totalPages;

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

    $.get(CHAT_ROUTES.backend + '?page=' + page + '&per_page=' + messagesPerPage, function(xml) {
        $("#loading").remove();                
        addMessages(xml);
    });
}

function loadOlderMessages(page) {
    $.get(CHAT_ROUTES.backend + '?page=' + page + '&per_page=' + messagesPerPage, function(xml) {
        const messagesAdded = addMessages(xml, true);

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
