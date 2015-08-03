<div id="def_options_ident" class="widget-box table-builder dataTables_wrapper">
    {{--
    <div class="widget-title">
        <span class="icon">
            <i class="glyphicon glyphicon-th"></i>
        </span>
        <h5>{{ $def['options']['caption'] }}</h5>
    </div>
    --}}
        @include('admin::tb.form')
        @include('admin::tb.table')
        
        @include('admin::tb.table.ui_overlay')

        <iframe id="submiter" name="submiter" style="display:none; visibility:hidden;"></iframe>                    
    </div>



</div>

<script type="text/javascript">
//jQuery(document).ready(function() {
    TableBuilder.init({
        ident: 'def_options_ident',
        table_ident: 'def_options_table_ident',
        form_ident: 'def_options_form_ident',
        action_url: '{{ $def['options']['action_url'] }}',
        onSearchResponse: function() {
            //Dashboard.initTooltips();
        },
    });
    TableBuilder.admin_prefix = '{{ $def['options']['admin_uri'] }}';
//});
</script>