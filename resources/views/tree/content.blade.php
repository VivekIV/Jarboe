
<div class="tb-tree-content-inner">
    
@if ($current->hasTableDefinition())

    {!! $table !!}
    
@else


    <div class="smart-form">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Title</th>
                <th width="15%">Template</th>
                <th width="10%">Slug</th>
                <th width="1%">Active</th>
                <th style="width: 1%; min-width: 105px;">
                    <a href="javascript:void(0);" onclick="Tree.showCreateForm('{{$current->id}}');" style="min-width: 70px; width: 100%;" class="btn btn-default btn-sm">Create</a>
                </th>
            </tr>
        </thead>

        <tbody>

        @if ($current->id == 1)
            <?php $current->children->prepend($current); ?>
        @endif

        @foreach($current['children'] as $item)
            @include('admin::tree.content_row')
        @endforeach

        </tbody>

        <tfoot>
        </tfoot>

    </table>
    </div>
    
    
    <style>
        .smart-form .popover-title {
            margin: 0;
            padding: 8px 14px;
        }
        .smart-form .popover-content {
            padding: 9px 14px;
        }
        .smart-form .editable-buttons {
            margin-left: 7px;
        }
    </style>
    <script>
    // FIXME: move to js file
        $(document).ready(function(){
            Tree.permissions = {!! json_encode(['create' => true]) !!};
            
            $('.tpl-editable').editable({
                url: window.location.href,
                source: [
                <?php /* FIXME: */ $tpls = $current->getTemplates(); ?>
                @foreach ($tpls as $capt => $tpl)
                    { value: '{{$capt}}', text: '{{$tpl['caption']}}' }, 
                @endforeach
                ],
                display: function(value, response) {
                    return false;   //disable this method
                },
                success: function(response, newValue) {
                    $(this).html(newValue);
                },
                params: function(params) {
                    //originally params contain pk, name and value
                    params.__structure_query_type = 'do_update_node';
                    return params;
                }
            });
        });
    </script>

@endif
    
</div>
 