jQuery(document).ready(function($) {
    const $authCheckbox = $('#mrs_gitdeploy_basic_auth_enabled');
    const $usernameField = $('#mrs_gitdeploy_basic_auth_username');
    const $passwordField = $('#mrs_gitdeploy_basic_auth_password');

    function toggleAuthFields() {
        if ($authCheckbox.is(':checked')) {
            $usernameField.prop('readonly', false);
            $passwordField.prop('readonly', false);
        } else {
            $usernameField.prop('readonly', true);
            $passwordField.prop('readonly', true);
        }
    }

    $authCheckbox.on('change', toggleAuthFields);
    toggleAuthFields();
});
