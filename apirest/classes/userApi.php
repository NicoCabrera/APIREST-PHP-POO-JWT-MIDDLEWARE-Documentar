<?php
require_once 'user.php';
require_once 'IGenericDAO.php';

use Slim\Http\UploadedFile;

class userApi extends User implements IGenericDAO
{
    public function getById($request, $response, $args)
    {
        $userid = $args['userid'];
        $user = User::getUserById($userid);
        if (!$user)
            {
            $rv = new stdclass();
            $rv->message = "Recurso no encontrado";
            $newResponse = $response->withJson($rv, 404);
        }
        else
            {
            $newResponse = $response->withJson($user, 200);
        }
        return $newResponse;
    }

    public function getAll($request, $response, $args)
    {
        $users = User::getAllUsers();
        $newResponse = $response->withJson($users, 200);
        return $newResponse;
    }

    public function insert($request, $response, $args)
    {

        $files = $request->getUploadedFiles();
        $file = "";
        if (array_key_exists("photo", $files)) {
            $file = $files['photo'];
        }
        $filename = "";
        $folder = "./uploads/";
        if (!empty($file))
            {
            $filename = $this->moveUploadedFile($folder, $file);
        }

        $newUserData = $request->getParsedBody();
        $password = crypt($newUserData["password"], "1af324D");

        $newUser = new User();
        $newUser->email = $newUserData["email"];
        $newUser->password = $password;
        $newUser->photo = $filename;
        $userid = $newUser->insertUser();

        $rv = new stdclass();
        $rv->message = "El usuario ha sido guardado exitosamente.";
        return $response->withJson($rv, 200);
    }

    public function update($request, $response, $args)
    {
        $newData = $request->getParsedBody();
        $userToUpdate = new User();
        $userToUpdate->userid = $newData['userid'];
        $userToUpdate->email = $newData['email'];
        $userToUpdate->password = crypt($newData['password'], "1af324D");
        $rv = new stdclass();
        if ($userToUpdate->updateUser()) {
            $rv->message = "El usuario ha sido actualizado con exitosamente.";
            $newResponse = $response->withJson($rv, 200);
        }
        else {
            $rv->message = "Hubo un error y no se ha podido actualizar. Comuniquese con el administrador de su sistema.";
            $newResponse = $response->withJson($rv, 404);
        }
        return $newResponse;
    }

    public function delete($request, $response, $args)
    {
        $userToDelete = $request->getParsedBody();
        $userid = $userToDelete['userid'];
        $user = new User();
        $user->userid = $userid;
        $rv = new stdclass();
        if ($user->deleteUser() > 0) {
            $rv->message = "Usuario eliminado exitosamente.";
            $response = $response->withJson($rv, 200);
        }
        else {
            $rv->message = "Usuario no encontrado.";
            $response = $response->withJson($rv, 404);
        }
        return $response;
    }



    public function validateUser($request, $response, $args)
    {
        try {
            $rv = new stdclass();
            $userData = $request->getParsedBody();
            $password = crypt($userData['password'], "1af324D");
            $email = $userData['email'];
            $user = User::getUserDataByEmailAndPassword($email, $password);
            if ($user != false) {

                $jwt = AuthJWT::getToken($user);
                $rv->jwt = $jwt;
                $rv->message = 'Usuario encontrado';
                $response = $response->withJson($rv, 200);
            }
            else {
                $rv->message = "El usuario no ha sido encontrado";
                $response = $response->withJson($rv, 404);
            }
            return $response;
        } catch (Exception $ex) {
            $rv->message = "Error desconocido. Comuniquese con el administrador de su sistema.";
            $response = $response->withJson($rv, 404);
            return $response;

        }

    }

    function registerUser($request, $response, $args)
    {
        $rv = new stdclass();

        $userData = $request->getParsedBody();
        $password = $userData['password'];
        $email = $userData['email'];

        if (User::userAlreadyExist($email)) {
            $rv->message = "El usuario ingresado ya existe";
            $response = $response->withJson($rv, 404);
        }
        else {
            $response = $this->insert($request, $response, $args);
            $user = new stdclass();
            $user->password = $password;
            $user->email = $email;
            $jwt = AuthJWT::getToken($user);
            $rv->message = "Usuario registrado exitosamente";
            $rv->jwt = $jwt;
            $response = $response->withJson($rv, 200);
        }
        return $response;
    }


    function moveUploadedFile($directory, UploadedFile $uploadedFile)
    {
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $basename = bin2hex(random_bytes(8));
        $filename = sprintf('%s.%0.8s', $basename, $extension);

        $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

        return $filename;
    }
}