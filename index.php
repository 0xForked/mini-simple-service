<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Slim\Http\UploadedFile;

require 'vendor/autoload.php';

$app = new \Slim\App([
    // Error details set to false if mode = Production
    // Debug set to False if mode = Production
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
    $pdo = new PDO("mysql:host=localhost;dbname=miniserv_rizal","foki","poke");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
};


$c['updir'] = __DIR__ . '/static/img';

$app->get('/', function (Request $req, Response $res) {
    $res->getBody()->write("Welcome to mini service");
    return $res;
});

// Get all data
$app->get('/product/list', function (Request $req, Response $res) {

    $category = $req->getParam('category');
    $search = $req->getParam('search');
    $toko = $req->getParam('toko');

    if ($category) {
        $stmt = $this->db->prepare('SELECT * FROM produk WHERE kategori=:kategori');
        $stmt->execute(['kategori' => $category]);
        $produk = $stmt->fetchAll();
    }

    if ($search) {
        $stmt = $this->db->prepare('SELECT * FROM produk WHERE nama_barang LIKE :search');
        $stmt->execute([':search' => '%'.$search.'%']);
        $produk = $stmt->fetchAll();
    }

    if ($toko) {
        $stmt = $this->db->prepare('SELECT * FROM produk WHERE uid=:uid');
        $stmt->execute(['uid' => $toko]);
        $produk = $stmt->fetchAll();
    }

    if (!$category && !$search && !$toko) {
        $produk = $this->db->query("SELECT * FROM produk")->fetchAll();
    }

    return $res->withJson(array(
        "value" => 1,
        "result" => $produk
    ));

});

// Get data by id
$app->get('/product/detail/{id}', function (Request $req, Response $res, array $args) {
    $stmt = $this->db->prepare('SELECT * FROM produk WHERE produk_id=:id');
    $stmt->execute(['id' => $args['id']]);
    $produk = $stmt->fetch();

    return $res->withJson(array(
        "value" => 1,
        "result" => $produk
    ));

});

// Insert data by id
$app->post('/product/store', function (Request $req, Response $res) {

    $params = $req->getParsedBody();

    // $directory = $this->get('updir');
    // $uploadedFiles = $req->getUploadedFiles();
    // $uploadedFile = $uploadedFiles['gambar'];
    // if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
    //     $filename = moveUploadedFile($directory, $uploadedFile);
    // }

    $row = [
        'uid' => $params['uid'],
        'nama_barang' => $params['nama_barang'],
        'kategori' => $params['kategori'],
        'harga' => $params['harga'],
        'keterangan' => $params['keterangan'],
        'pic' => $params['pic'],
        'lat' => $params['lat'],
        'lon' => $params['lon']
    ];

    $status = $this->db
            ->prepare(
                    "INSERT INTO produk SET
                    uid=:uid,
                    nama_barang=:nama_barang,
                    kategori=:kategori,
                    harga=:harga,
                    keterangan=:keterangan,
                    pic=:pic,
                    lat=:lat,
                    lon=:lon;"
                )
            ->execute($row);


    if ($status) {
        return $res->withJson(array(
            "value" => 1,
            "Message" => "Berhasil dibuat"
        ));
    } else {
        return $res->withJson(array(
            "value" => 0,
            "Message" => "Gagal dibuat"
        ));
    }

});

// Update data by id
$app->post('/product/update/{id}', function (Request $req, Response $res, array $args) {

    $params = $req->getParsedBody();

    // $directory = $this->get('updir');
    // $uploadedFiles = $req->getUploadedFiles();
    // $uploadedFile = $uploadedFiles['gambar'];
    // if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
    //     $filename = moveUploadedFile($directory, $uploadedFile);
    // }

    $row = [
        'id' => $args['id'],
        'nama_barang' => $params['nama_barang'],
        'kategori' => $params['kategori'],
        'harga' => $params['harga'],
        'keterangan' => $params['keterangan'],
        'pic' => $params['pic'],
        'lat' => $params['lat'],
        'lon' => $params['lon']
    ];

    $status = $this->db
                ->prepare(
                        "UPDATE produk SET
                        nama_barang=:nama_barang,
                        kategori=:kategori,
                        harga=:harga,
                        keterangan=:keterangan,
                        pic=:pic,
                        lat=:lat,
                        lon=:lon
                        WHERE produk_id=:id;"
                    )
                ->execute($row);

     if ($status) {
         return $res->withJson(array(
            "value" => 1,
            "Message" => "Berhasil diupdate"
        ));
    } else {
        return $res->withJson(array(
            "value" => 0,
            "Message" => "Gagal diupdate"
        ));
    }

});

// Delete data by id
$app->get('/product/delete/{id}', function (Request $req, Response $res, array $args) {
    $where = ['id' => $args['id']];
    $exe = $this->db->prepare("DELETE FROM produk WHERE produk_id=:id")->execute($where);

    if ($exe) {
        return $res->withJson(array(
            "value" => 1,
            "Message" => "Berhasil dihapus"
        ));
    } else {
        return $res->withJson(array(
            "value" => 0,
            "Message" => "Gagal dihapus"
        ));
    }

});


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
