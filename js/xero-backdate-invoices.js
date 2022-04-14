jQuery( document ).ready( function( $ ) {
    // Add import button at top of page
    $( '<span class="spinner"></span><a href="#" class="page-title-action" id="xero-backdate-invoices">' + xero_backdate_invoices.import_button_text + '</a>' ).insertAfter( '#mainform h2:first-of-type' );

    // Run AJAX to add events for existing subscriptions
    $( '.wrap' ).on( 'click', '#xero-backdate-invoices', function() {
        $( this ).attr( 'disabled', 'disabled' ).text( xero_backdate_invoices.updating_text ).prev( '.spinner' ).css( { 'visibility': 'visible', 'float': 'none', 'margin': '-5px 10px 5px' } );
        getOrders();
    } );
    // Loop to update orders with xero invoices
    function updateXero( orders ) {
        console.log( 'updating', orders );
        var orders_total = orders.length;
        var current_total = 0;

        // Run ajax request
        function runRequest() {
            // Check to make sure there are more orders to update
            if( orders.length > 0 ) {

                var current = orders.splice( 0, 10 );

                // Make the AJAX request with the given orders
                var data = {
                    action: 'xero_backdate_invoices_send_invoices',
                    orders: current
                };
                $.post( ajaxurl, data, function( response ) {
                    console.log( response.data );
                    current_total = parseInt( current.length ) + parseInt( current_total );
                    if( current_total == orders_total ) {
                        window.location.reload();
                    }
                } ).done( function() {
                    window.setTimeout( runRequest, 20000 );
                } );
            }
        }

        runRequest();
    }

    function getOrders() {
        var data = {
            action: 'xero_backdate_invoices_get_orders'
        };
        $.post( ajaxurl, data, function( response ) {
            var orders = response.data;
            if( orders.length > 0 ) {
                updateXero( orders );
            } else {
                console.log( 'no orders found:', orders );
            }
        } );
    }
} );
