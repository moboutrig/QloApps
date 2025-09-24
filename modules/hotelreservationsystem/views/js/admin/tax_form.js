$(document).ready(function() {
    function toggleTaxFields() {
        if ($('#type').val() == 2) {
            $('#rate').closest('.form-group').hide();
            $('#amount').closest('.form-group').show();
        } else {
            $('#rate').closest('.form-group').show();
            $('#amount').closest('.form-group').hide();
        }
    }

    toggleTaxFields();

    $('#type').on('change', function() {
        toggleTaxFields();
    });
});
