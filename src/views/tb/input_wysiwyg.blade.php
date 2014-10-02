<div id="{{$name}}-wysiwyg">{{ $value }}</div>
<textarea id="{{$name}}" name="{{ $name }}" style="display:none;" class="hidden">{{ $value }}</textarea>
<script type="text/javascript">
    jQuery(document).ready(function() {
      jQuery('#{{$name}}-wysiwyg').summernote({
          lang: 'ru-RU',
          onblur: function(e) {
              jQuery('#{{$name}}').html(jQuery('#{{$name}}-wysiwyg').code());
          },
          onImageUpload: function(files, editor, $editable) {
              TableBuilder.uploadImageFromWysiwygSummertime(files, editor, $editable);
          },
          onpaste: function(e) {
              var $note = jQuery(this);
              
              setTimeout(function () {
                  //this kinda sucks, but if you don't do a setTimeout, 
                  //the function is called before the text is really pasted.
                  TableBuilder.doEmbedToText($note);
              }, 1);
          }
      });
    });
</script>