jQuery(document).ready(function($) {
    function checkFields() {
        var username = $('#wp_gitdeploy_username').val().trim();
        var repo = $('#wp_gitdeploy_repo').val().trim();
        var button = $('#test-repo-button');

        // Enable or disable the button based on the trimmed input values
        if (username && repo) {
            button.removeAttr('disabled');
        } else {
            button.attr('disabled', 'disabled');
        }
    }

    function testGitHubRepo() {
        var username = $('#wp_gitdeploy_username').val().trim();
        var repo = $('#wp_gitdeploy_repo').val().trim();
        var branch = $('#wp_gitdeploy_repo_branch').val().trim();

        if ( ! branch ) {
            branch = 'main';
        }

        if (username && repo) {
            var url = 'https://github.com/' + username + '/' + repo + '/' + 'tree/' + branch + '/';
            window.open(url, 'Test WP GitDeploy repo', 'height=800,width=1100');

            // Show the message after opening the new window
            showTestMessage();
        }
    }

    function showTestMessage() {
        // Remove any existing message with the unique class
        $('.test-repo-message').remove();
    
        // Create a new message element with a unique class
        var message = $('<p>', {
            text: 'If you saw your repository in the new window, and not a 404, you should be good to save these!',
            class: 'test-repo-message',
            css: { color: 'red' }
        });
    
        // Insert the message after the Test button
        $('#test-repo-button').after(message);
    }    

    // Initial check on page load to set the correct button state
    checkFields();

    // Attach event listeners
    $('#wp_gitdeploy_username, #wp_gitdeploy_repo').on('input', checkFields);
    $('#test-repo-button').on('click', testGitHubRepo);
});

function changeTokenPlaceholderText() {
    document.getElementById('wp_gitdeploy_token').placeholder = 'Now, paste it here!';
}
