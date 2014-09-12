<?php namespace Yaro\TableBuilder\Handlers;

use Yaro\TableBuilder\TableBuilderController;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Session;


class ViewHandler {

    protected $controller;


    public function __construct(TableBuilderController $controller)
    {
        $this->controller = $controller;
    } // end __construct

    public function showList()
    {
        $table = View::make('admin::tb.table_builder');
        $table->def  = $this->controller->getDefinition();
        $table->rows = $this->controller->query->getRows();
        $table->controller = $this->controller;

        return $table;
    } // end showList

    public function showEditForm($id = false)
    {
        $table = View::make('admin::tb.modal_form');
        if ($id) {
            $table = View::make('admin::tb.modal_form_edit');
        }
        
        $table->def = $this->controller->getDefinition();
        $table->controller = $this->controller;

        $table->is_blank = true;
        if ($id) {
            $table->row = $this->controller->query->getRow($id);
            $table->is_blank = false;
        }

        return $table->render();
    } // end showEditForm
    
    public function getRowHtml($data)
    {
        // FIXME: primary key
        $data['values'] = $this->controller->query->getRow($data['id']);
        
        $row = View::make('admin::tb.single_row');
        $row->controller = $this->controller;
        $row->def        = $this->controller->getDefinition();
        $row->data       = $data;
        
        return $row->render();
    } // end getRowHtml

}
