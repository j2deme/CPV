<?php
session_cache_limiter(false);
session_start();
date_default_timezone_set('America/Mexico_City');
require 'vendor/autoload.php';
include_once 'arrays.php';

$app = new \Slim\Slim();
$app->add(
  new \Slim\Middleware\SessionCookie(array('secret' => 'myappsecret'))
);
$app->config(array(
  'debug' => true,
  'templates.path' => './views',
  'log.enabled' => false,
  'view' => new \Slim\Views\Twig()
));
$view = $app->view();
$view->parserExtensions = array(
    new \Slim\Views\TwigExtension(),
);

use Illuminate\Database\Capsule\Manager as Capsule;
$capsule = new Capsule;
$capsule->addConnection(array(
  'driver' => 'mysql',
  'host' => 'localhost',
  'database'  => 'cpv',
  'username'  => 'root',
  'password'  => 'root',
  'prefix' => '',
  'charset' => 'utf8',
  'collation' => 'utf8_general_ci',
));
$capsule->bootEloquent();
$capsule->setAsGlobal();
$app->db = $capsule->connection();

$app->get('/', function () use ($app) {
  $data = array();
  $app->render('index.twig', $data);
})->name('root');

$app->get('/login', function() use($app) {
  $data = array();
  $app->render('login.twig', $data);
})->name('login');

$app->post('/login', function() use($app){
  $post = (object) $app->request->post();
  $usuario = $app->db->table('personas')->where('usuario', $post->usuario)->first();
  $password = md5(trim(isset($post->password) ? $post->password : ""));
  if($usuario != null){
    $usuario = (object) $usuario;
    if($password == $usuario->password){
      $app->redirect($app->urlFor('view-persona',array('id'=>$usuario->id)));
    } else {
      $app->flash('error', 'Contraseña Incorrecta.');
      $app->flashKeep();
      $app->redirect($app->urlFor('login'));
    }
  } else {
    $app->flash('error', "El usuario no existe, <a href=\"".$app->urlFor('new-persona')."\" class= \"alert-link\">registrate</a>.");
    $app->flashKeep();
    $app->redirect($app->urlFor('login'));
  }
})->name('check-login');

$app->get('/alta-propietario', function() use ($app,$carreras){
  $data = array();
  $data['carreras'] = $carreras;
  $app->render('persona.twig', $data);
})->name('new-persona');

$app->post('/alta-propietario', function() use ($app){
  $now = new DateTime('now');
  $post = (object) $app->request->post();
  $data = array(
    'nombre' => ucwords(strtolower(trim($post->nombre))),
    'usuario' => trim($post->usuario),
    'password' => md5(trim($post->password)),
    'created_at' => $now,
    'updated_at' => $now
  );
  if(isset($post->no_control)){
    $data['no_control'] = trim($post->no_control);
  }
  if(isset($post->semestre) && $post->semestre != 0){
    $data['semestre'] = $post->semestre;
  }
  if(isset($post->carrera) && $post->carrera != '0'){
    $data['carrera'] = $post->carrera;
  }
  $id = $app->db->table('personas')->insertGetId($data);
  $app->redirect($app->urlFor('view-persona',array('id'=>$id)));
})->name('save-persona');

$app->get('/p/:id', function($id) use($app,$mx_states){
  $data = array();
  $data['states'] = $mx_states;
  $data['persona'] = $app->db->table('personas')->where('id', $id)->first();
  $data['vehiculos'] = $app->db->table('vehiculos')->where('id_persona', $id)->get();
  $app->render('persona.twig', $data);
})->name('view-persona');

$app->get('/p/:id/alta-vehiculo', function($id) use($app,$mx_states,$colores,$tipos){
  $data = array();
  $data['id_persona'] = $id;
  $data['states'] = $mx_states;
  $data['colores'] = $colores;
  $data['tipos'] = $tipos;
  $app->render('vehiculo.twig', $data);
})->name('new-vehiculo');

$app->get('/p/:id_p/editar-vehiculo/:id_v', function($id_p,$id_v) use($app,$mx_states,$colores,$tipos){
  $data = array();
  $data['vehiculo'] = $app->db->table('vehiculos')->where('id', $id_v)->first();
  $data['id_persona'] = $id_p;
  $data['states'] = $mx_states;
  $data['colores'] = $colores;
  $data['tipos'] = $tipos;
  $app->render('vehiculo.twig', $data);
})->name('edit-vehiculo');

$app->post('/alta-vehiculo', function() use($app){
  $now = new DateTime('now');
  $post = (object) $app->request->post();
  $data = array(
    'placas'      => strtoupper(trim($post->placas)),
    'id_persona'  => $post->id_persona,
    'estado'      => $post->estado,
    'marca'       => ucwords(strtolower($post->marca)),
    'modelo'      => trim($post->modelo),
    'tipo'        => $post->tipo,
    'color'       => $post->color,
    'descripcion' => trim($post->descripcion)
  );
  if(isset($post->id_vehiculo) && $post->id_vehiculo != ""){
    $data['updated_at'] = $now;
    $id = $app->db->table('vehiculos')->where('id',$post->id_vehiculo)->update($data);
  } else {
    $data['created_at'] = $now;
    $data['updated_at'] = $now;
    $id = $app->db->table('vehiculos')->insert($data);
  }
  $app->redirect($app->urlFor('view-persona',array('id' => $post->id_persona)));
})->name('save-vehiculo');

$app->get('/p/:id_p/eliminar-vehiculo/:id_v', function($id_p,$id_v) use($app){
  $id = $app->db->table('vehiculos')->where('id', $id_v)->delete();
  $app->redirect($app->urlFor('view-persona',array('id' => $id_p)));
})->name('delete-vehiculo');

$app->get('/lista/registros', function() use($app) {
  $data['registros'] = $app->db->table('personas')
    ->leftJoin('vehiculos', 'personas.id', '=', 'vehiculos.id_persona')
    ->select('personas.nombre', 'personas.no_control','personas.semestre','personas.carrera','vehiculos.placas','vehiculos.estado','vehiculos.tipo','vehiculos.marca','vehiculos.modelo','vehiculos.color')
    ->get();
  $app->render('list-all.twig', $data);
})->name('list-alumnos');

$app->get('/initdb', function() use($app) {
  $schema = $app->db->getSchemaBuilder();
  if($schema->hasTable('personas')){
    $schema->table('personas', function($table) {
      $table->string('usuario');
      $table->string('password');
    });
  } else {
    $schema->create('personas', function($table) {
      $table->increments('id');
      $table->text('nombre');
      $table->string('usuario');
      $table->string('password');
      $table->string('no_control',12)->nullable();
      $table->integer('semestre')->nullable();
      $table->string('carrera')->nullable();
      $table->timestamps();
    });
  }

  if($schema->hasTable('vehiculos')){
    $schema->table('vehiculos', function($table) {
      $table->boolean('gafete')->default(false);
    });
  } else {
    $schema->create('vehiculos', function($table) {
      $table->increments('id');
      $table->integer('id_persona');
      $table->string('placas');
      $table->string('estado');
      $table->string('tipo');
      $table->string('marca');
      $table->integer('modelo');
      $table->string('color');
      $table->text('descripcion')->nullable();
      $table->boolean('gafete')->default(false);
      $table->timestamps();
    });
  }
});

$app->run();
?>
