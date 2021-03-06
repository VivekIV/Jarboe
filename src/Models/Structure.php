<?php

namespace Yaro\Jarboe\Models;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use Jarboe;


class Structure extends \Baum\Node 
{
     
    protected $table = 'structure';
    protected $parentColumn = 'parent_id';

    protected $nodeUrl = null;
    protected $breadcrumbs = array();
    
    protected $nodeActiveField = 'is_active';
    protected $nodeActiveFieldOptions = [];
    
    
    public static function flushCache()
    {
        //Cache::tags('tree-'. mb_strtolower(static::class))->flush();
    } // end flushCache
    
    public function addBreadcrumb($node)
    {
        array_unshift($this->breadcrumbs, $node);
    } // end addBreadcrumb
    
    public function getBreadcrumbs($withSelf = false)
    {
        if ($withSelf) {
            $this->addBreadcrumb($this);
        }
        
        return collect($this->breadcrumbs)->reverse();
    } // end getBreadcrumbs
    
    public function setSlugAttribute($value)
    {
        // FIXME:
        $slug = Jarboe::urlify($value);
        
        $slugs = $this->where('parent_id', $this->parent_id)
                      ->where('id', '<>', $this->id)
                      ->whereRaw("slug REGEXP '^{$slug}(-[0-9]*)?$'")
                      ->lists('slug');

        $slugCount = 0;
        if (array_search($slug, $slugs->toArray()) !== false) {
            foreach ($slugs as $existedSlug) {
                if (preg_match('~(\d+)$~', $existedSlug, $matches)) {
                    $slugCount = $slugCount > $matches[1] ? $slugCount : $matches[1];
                }
            }
            $slugCount++;
        }
        
        $slug = $slugCount ? $slug .'-'. $slugCount : $slug;
        
        $this->attributes['slug'] = $slug;
    } // end setSlugAttribute

    public function hasTableDefinition()
    {
        $templates = static::getTemplates();
        $template = config('jarboe.c.structure.default');
        if (isset($templates[$this->template])) {
            $template = $templates[$this->template];
        }

        return $template['type'] == 'table';
    } // end hasTableDefinition

    public function setUrl($url)
    {
        $this->nodeUrl = $url;
    } // end setUrl

    public function getUrl()
    {
        if (is_null($this->nodeUrl)) {
            $this->nodeUrl = $this->getGeneratedUrl();
        }
        return $this->nodeUrl;
    } // end getUrl
    
    public function getNodeActiveField()
    {
        return $this->nodeActiveField;
    } // end getNodeActiveField
    
    public function getNodeActiveFieldOptions()
    {
        return $this->nodeActiveFieldOptions;
    } // end getNodeActiveFieldOptions

    public function isActive($setIdent = false)
    {
        $activeField = $this->getNodeActiveField();
        $options = $this->getNodeActiveFieldOptions();
        
        if (!$options) {
            return $this->$activeField == 1;
        }
        
        if ($setIdent) {
            return !!preg_match('~'. preg_quote($setIdent) .'~', $this->$activeField);
        }
        
        foreach ($options as $ident => $caption) {
            if (preg_match('~'. preg_quote($ident) .'~', $this->$activeField)) {
                return true;
            }
        }
        
        return false;
    } // end isActive

    public function getGeneratedUrl()
    {
        $all = $this->getAncestorsAndSelf();

        $slugs = array();
        foreach ($all as $node) {
            if ($node->slug == '/') {
                continue;
            }
            $slugs[] = $node->slug;
        }

        return implode('/', $slugs);
    } // end getGeneratedUrl
    
    
    protected static function registerSingleRoute($urlPath, $node)
    {
        $model = static::class;
        $templates = $model::getTemplates();
        
        if (isset($templates[$node->template]) && $templates[$node->template]['type'] == 'table') {
            $routeParams = isset($templates[$node->template]['route']) ? $templates[$node->template]['route'] : [];
            
            $route = Route::get($urlPath .'/'. $routeParams['slug'], function() use($node, $model, $templates, $routeParams) {
                list($controller, $method) = explode('@', $routeParams['action']);
                $controller = '\\'. ltrim($controller, '\\');
                return app()->make($controller)->callAction(
                    'init', 
                    [$node]
                )->callAction($method, Route::current()->parameters());
            });
            
            foreach ($routeParams['patterns'] as $name => $pattern) {
                $route->where($name, $pattern);
            }
        }
        
        Route::get($urlPath, function() use($node, $model, $templates)
        {
            if (!isset($templates[$node->template])) {
                // just to be gentle with web crawlers
                abort(404);
            }
            list($controller, $method) = explode('@', $templates[$node->template]['action']);
            $controller = '\\'. ltrim($controller, '\\');
            return app()->make($controller)->callAction(
                'init', 
                [$node]
            )->callAction($method, Route::current()->parameters());
        });
    } // end registerSingleRoute
    
    public static function registerRoutes()
    {
        $model = static::class;
        $tags = array('jarboe', 'j_tree', 'tree-'. mb_strtolower($model));
        
        // FIXME: make a little bit pretty
        $tree = false;//Cache::tags($tags)->get('tree');
        if ($tree) {
            foreach ($tree as $node) {
                self::registerSingleRoute($node->getUrl(), $node);
            }
        } else {
            $nodeUrl = '';
            
            // HACK: if we dont have table, so dont crash whole site
            try {
                $tree = $model::all(); 
            } catch (\Exception $e) {
                return;
            }
            
            
            $clone = clone $tree;
            $clone = $clone->toArray();
            //
            $clone = array_combine(array_column($clone, 'id'), $clone);
        
            foreach ($tree as $node) {
                $nodeUrl = $model::recurseTree($node, $clone, $node, $tree);
                $node->setUrl($nodeUrl);
                
                self::registerSingleRoute($nodeUrl, $node);
            }
        
            //Cache::tags($tags)->put('tree', $tree, 1440);
        }
        
        
        unset($clone);
        unset($tree);
    } // end registerRoutes
    
    protected static function recurseTree(&$current, $tree, $node, $objectTree, &$slugs = [])
    {
        if (!$node['parent_id']) {
            return $node['slug'];
        }
    
        $slugs[] = $node['slug'];
        $idParent = $node['parent_id'];
        if ($idParent) {
            $parent = $objectTree->where('id', $idParent)->first();//$tree[$idParent];
            $current->addBreadcrumb($parent);
            self::recurseTree($current, $tree, $parent, $objectTree, $slugs);
        }
    
        return implode('/', array_reverse($slugs));
    } // end recurseTree
    
    public static function getTemplates()
    {
        return [
            'default mainpage template' => array(
                'caption' => 'Default template',
                'type' => 'node', // table | node
                'action' => 'Yaro\Jarboe\Http\Controllers\TreeController@showThemeMain',
                'definition' => '',
                'node_definition' => \Yaro\Jarboe\Definition\Node::class,
                'check' => function() {
                    return true;
                },
            ),
            /*
            'table sample' => array(
                'caption' => 'Test table',
                'type' => 'table', 
                'action' => 'HomeController@showPage',
                'definition' => \App\Definitions\Test::class,
                'node_definition' => \Yaro\Jarboe\Definition\Node::class,
                'check' => function() {
                    return true;
                },
                'route' => [
                    'action' => 'App\Http\Controllers\SomeController@showTableRow',
                    'slug' => 'well-{id}',
                    'patterns' => [
                        'id' => '[0-9]+'
                    ],
                ]
            ),
            'breadcrumbs' => array(
                'caption' => 'Breadcrumbs',
                'type' => 'node', 
                'action' => 'App\Http\Controllers\SomeController@breadcrumbs',
                'definition' => 'node', 
                'node_definition' => 'node',
                'check' => function() {
                    return true;
                },
            ),
            */
        ];
    } // end getTemplates
    
}