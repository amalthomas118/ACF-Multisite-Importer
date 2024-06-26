jQuery(document).ready(function($) {
    $('#select-all').click(function() {
        var isChecked = $(this).is(':checked');
        $('input[name="sites[]"]').prop('checked', isChecked);
    });
});