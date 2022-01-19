<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

class APIInfo
{
    public static function getInfo(Request $request)
    {
        $routeCollection = \Illuminate\Support\Facades\Route::getRoutes();
        $paths = [];
        foreach ($routeCollection as $value) {
            if (isset($value->action['middleware'])) {
                if (
                    (is_array($value->action['middleware']) && in_array('api', $value->action['middleware']))
                    || $value->action['middleware'] == 'api'
                    ) {
                    $paths[] = [
                        'uri' => $value->uri,
                        'methods' => $value->methods,
                        //'middleware' => $value->action['middleware'],
                        //'controller' => @$value->action['controller'],
                    ];
                }
            }
        }
    
        foreach ($paths as $key => $path) {

            $paths[$key]['friendly'] = array_diff($path['methods'], ['HEAD'])[0] . " {{baseUrl}}{$path['uri']}";
            
            if (substr($path['uri'], -5) == '_info') {
                $paths[$key]['body'] = (Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])->get(url($path['uri'])))->json();
                $body = $paths[$key]['body'];
            }

            if (isset($path['methods'][0]) && ($path['methods'][0] == 'POST' || $path['methods'][0] == 'PUT'))
            {
                if (isset($body)) {
                    $paths[$key]['body'] = $body;
                }
            }
            
        }
    
        if (isset($request->list)) {
            $list = [];
            foreach ($paths as $key => $path) {
                if (isset($path['body']['fields'])) {
                    $list[$path['friendly']] = $path['body']['fields'];
                } else {
                    $list[$path['friendly']] = null;
                }
            }
            return response()->json($list);
        }

        if (isset($request->download)) {
            switch ($request->download) {
                case 'postman':
                    return response()->download(self::getPostmanJson($paths), 'postman_api_collection.json', [
                        'Content-Type' => 'application/json'
                    ]);
            }
        }
    
        return response()->json($paths);
    }

    public static function getPostmanJson($paths)
    {
        //dd($paths);
        $output = (new class($paths) {
            public function __construct($paths)
            {
                $this->info = (new class($paths) {
                    public $schema = 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json';
                    public function __construct()
                    {
                        $this->name = 'API ' . date('r', time());
                    }
                });
                $this->item = [];
            }
        });

        //dd($paths);
        foreach ($paths as $key => $path) {
            //dd(config('api.apis'));
            $output->item[] = (new class($path) {
                public function __construct($path)
                {
                    $this->name = @$path['methods'][0] . ' ' . $path['uri'];
                    $this->request = (new class($path) {
                        public function __construct($path)
                        {
                            $this->auth = (new class($path) {
                                public $type = 'bearer';
                                public function __construct($path)
                                {
                                    $this->bearer = [
                                        (new class {
                                            public $key = 'token';
                                            public $value = '{{TOKEN}}';
                                            public $type = 'string';
                                        })
                                    ];
                                }
                            });
                            $this->method = $path['methods'][0];
                            $this->header = [
                                (new class {
                                    public $key = 'Content-Type';
                                    public $value = 'application/json';
                                    public $type = 'text';
                                }),
                                (new class {
                                    public $key = 'Accept';
                                    public $value = 'application/json';
                                    public $type = 'text';
                                })
                            ];
                            if (isset($path['body']['fields'])) {
                                //dd($path['body']['fields']);
                                $this->body = (new class($path['body']['fields']) {
                                    public function __construct($fields)
                                    {
                                        $this->mode = 'raw';
                                        $this->raw = [];
                                        foreach ($fields as $field) {
                                            $this->raw[$field] = '?';
                                        }
                                        $this->raw = json_encode($this->raw, JSON_FORCE_OBJECT | JSON_PRETTY_PRINT);
                                    }
                                });
                            }
                            $this->url = (new class($path) {
                                public function __construct($path)
                                {
                                    $this->raw = '{{baseUrl}}' . $path['uri'];
                                    $this->host = [
                                        '{{baseUrl}}' . $path['uri']
                                    ];
                                    $apis = config('api.apis');
                                    if (is_array($apis)) {
                                        foreach ($apis as $api) {
                                            $this->raw = str_replace(@$api['path'], '', $this->raw);
                                            foreach ($this->host as $key => $host) {
                                                $this->host[$key] = str_replace(@$api['path'], '', $host);
                                            }
                                        }
                                    }
                                }
                            });
                        }
                    });

                    $this->response = [];
                }
            });
        }

        File::put('/tmp/larab__postman_api_export', json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return '/tmp/larab__postman_api_export'; 

    }

}