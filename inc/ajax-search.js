jQuery(document).ready(function() {
    jQuery("#searchKeyword").autocomplete({
        source: function(request, response){  
            jQuery.ajax({
            type: 'POST',
            dataType: "json",
            url: '/wp-admin/admin-ajax.php',
            data: {
                action: 'aw_gb_suggest',
                search_string: request.term,
            },
            success: function(data){
                response(jQuery.map(data,function(item) {
                    return {
                        label: item.name,
                        value: item.name
                    }
                }));
            },
            error: function(MLHttpRequest, textStatus, errorThrown){
                jQuery("#results").html('');
                jQuery("#results").append("There seems to be a problem with this feature. Please try again soon.");

            }
        });
        },  
        
        minLength: 2,
        delay: 0,
        select: function(event,ui) {
            jQuery("#searchKeyword").val(ui.item.value);
            do_ajax_search();

        }
    });

    jQuery("#submitSearch").click(function(){
    	do_ajax_search();
    });

    jQuery("#searchKeyword").keypress(function(e) {
        if(e.which == 13) {
            do_ajax_search();
            jQuery("#searchKeyword").autocomplete('close');
            return false;
        }
    });
    
    function do_ajax_search() {
        var search_string = jQuery("#searchKeyword").val();
        if (search_string.length > 0) {
            var results = jQuery("#results");
            results.html('').append("Searching...");
            
            jQuery.ajax({
                type: 'POST',
                url: '/wp-admin/admin-ajax.php',
                data: {
                    action: 'aw_gb_do_search',
                    search_string: search_string,
                },
                success: function(data, textStatus, XMLHttpRequest){
                    results.html('').append(data);
                },
                error: function(MLHttpRequest, textStatus, errorThrown){
                    results.html('Oh no! Something\'s gone wrong! Try again in a few minutes and things should be sorted out.');
                }
            });
        }
        else {
            results.html('').append("Please enter an item to search!");
        }
    }

});