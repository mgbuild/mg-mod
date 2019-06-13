/**
 * @file
 * Handle the form field behaviors.
 */
(function ($) {
    Drupal.behaviors.automatic_node_downloader = {
        attach: function (context, settings) {
          
            $('#mid-menu-dept-level').change(function () {
               
                $(document).ajaxComplete(function (event, xhr, settings) {
                    
                    $('[data-drupal-selector="edit-mid-field-names-all"]').removeClass('form-checkbox');
                    // add multiple select / deselect functionality
                    
                    $('[data-drupal-selector="edit-mid-field-names-all"]').on('click',function(){
                        if(this.checked){
                            $('.form-checkbox').each(function(){
                                this.checked = true;
                            });
                        }else{
                             $('.form-checkbox').each(function(){
                                this.checked = false;
                            });
                        }
                    });

                    $('.form-checkbox').on('click',function(){
                        if($('.form-checkbox:checked').length == $('.form-checkbox').length){
                            $('[data-drupal-selector="edit-mid-field-names-all"]').prop('checked',true);
                        }else{
                            $('[data-drupal-selector="edit-mid-field-names-all"]').prop('checked',false);
                        }
                    });
                });
            });
        }
    };
})(jQuery);

//Reset Select starting menu items 
function validation_set(){
    jQuery('#mid-menu-item-id option').remove();
    
}
//Reset Menu depth Level
function validation_level(){
    jQuery('#mid-menu-dept-level option').val('none');
    
}