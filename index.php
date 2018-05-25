<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Slim\Http\UploadedFile;

require 'vendor/autoload.php';

$app = new \Slim\App([
    'displayErrorDetails' => true,
    'debug' => true,
]);

$c = $app->getContainer();

$c['errorHandler'] = function ($c) {
    return function ($request, $response, $exception) use ($c) {
        return $c['response']
            ->withStatus(500)
            ->withHeader('Content-Type', 'text/html')
            ->write('Something went wrong!');
    };
};

$c['notFoundHandler'] = function ($c) {
    return function ($request, $response) use ($c) {
        return $c['response']
            ->withStatus(404)
            ->withHeader('Content-Type', 'text/html')
            ->write('Page not found');
    };
};

$c['notAllowedHandler'] = function ($c) {
    return function ($request, $response, $methods) use ($c) {
        return $c['response']
            ->withStatus(405)
            ->withHeader('Allow', implode(', ', $methods))
            ->withHeader('Content-type', 'text/html')
            ->write('Method must be one of: ' . implode(', ', $methods));
    };
};

$c['db'] = function ($c) {
    $pdo = new PDO("mysql:host=localhost;dbname=name","user","password");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
};


$c['updir'] = __DIR__ . '/static/img';

$app->get('/', function (Request $req, Response $res) {
    $res->getBody()->write("Welcome to mini service");
    return $res;
});


// ================= EXAMPLE ROUTE ========================== //

// Get all data
$app->get('/resep/list', function (Request $req, Response $res) {
    $resep = $this->db->query("SELECT * FROM resep")->fetchAll();
    return $res->withJson($resep);
});

// Get data by id
$app->get('/resep/detail/{id}', function (Request $req, Response $res, array $args) {
    $stmt = $this->db->prepare('SELECT * FROM resep WHERE id = :id');
    $stmt->execute(['id' => $args['id']]);
    $resep = $stmt->fetch();
    return $res->withJson($resep);
});

// Insert data by id
$app->post('/resep/store', function (Request $req, Response $res) {

    $params = $req->getParsedBody();

    $directory = $this->get('updir');
    $uploadedFiles = $req->getUploadedFiles();
    $uploadedFile = $uploadedFiles['gambar'];
    if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
        $filename = moveUploadedFile($directory, $uploadedFile);
    }

    $row = [
        'title' => $params['title'],
        'desc' => $params['desc'],
        'langkah' => $params['langkah'],
        'bahan' => $params['bahan'],
        'gambar' => $filename
    ];

    $status = $this->db
                ->prepare(
                        "INSERT INTO resep SET
                        title=:title,
                        desc=:desc,
                        langkah=:langkah,
                        bahan=:bahan,
                        gambar=:gambar;"
                    )
                ->execute($row);

    if ($status) {
        return $res->getBody()->write("Data created!");
    }

});

// Update data by id
$app->post('/resep/update/{id}', function (Request $req, Response $res, array $args) {

    $params = $req->getParsedBody();

    $directory = $this->get('updir');
    $uploadedFiles = $req->getUploadedFiles();
    $uploadedFile = $uploadedFiles['gambar'];
    if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
        $filename = moveUploadedFile($directory, $uploadedFile);
    }

    $row = [
        'id' => $args['id'],
        'title' => $params['title'],
        'desc' => $params['desc'],
        'langkah' => $params['langkah'],
        'bahan' => $params['bahan'],
        'gambar' => $filename
    ];

    $status = $this->db
                ->prepare(
                        "UPDATE resep SET
                        title=:title,
                        desc=:desc,
                        langkah=:langkah,
                        bahan=:bahan,
                        gambar=:gambar
                        WHERE id=:id;"
                    )
                ->execute($row);

    if ($status) {
        return $res->getBody()->write("Data updated!");
    }
});

// Delete data by id
$app->get('/resep/delete/{id}', function (Request $req, Response $res, array $args) {
    $where = ['id' => $args['id']];
    $exe = $this->db->prepare("DELETE FROM resep WHERE id=:id")->execute($where);
    if ($exe) {
        return $res->getBody()->write("Data deleted!");
    }
});

// ================= EXAMPLE ROUTE ========================== //

function moveUploadedFile($directory, UploadedFile $uploadedFile)
{
    $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
    $basename = bin2hex(random_bytes(8));
    $filename = sprintf('%s.%0.8s', $basename, $extension);
    $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);
    return $filename;
}

function getBaseUrl()
{
    $currentPath = $_SERVER['PHP_SELF'];
    //$pathInfo = "/static/img";
    $hostName = $_SERVER['HTTP_HOST'];
    $protocol = strtolower(substr($_SERVER["SERVER_PROTOCOL"],0,5))=='https'?'https':'http';
    return $protocol.'://'.$hostName;
}


$app->run();
