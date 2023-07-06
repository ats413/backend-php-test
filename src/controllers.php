<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app['twig'] = $app->share($app->extend('twig', function ($twig, $app) {
    $twig->addGlobal('user', $app['session']->get('user'));

    return $twig;
}));


$app->get('/', function () use ($app) {
    return $app->redirect('/login');
});


$app->match('/login', function (Request $request) use ($app) {

    if(null !== $app['session']->get('user')) {
        return $app->redirect('/login');
    }
    $username = $request->get('username');
    $password = $request->get('password');
    if ($username) {
        $sql = "SELECT * FROM users WHERE username = '$username' and password = '$password'";
        $user = $app['db']->fetchAssoc($sql);

        if ($user) {
            $app['session']->set('user', $user);
            return $app->redirect('/todo');
        }
    }

    return $app['twig']->render('login.html', array());
});


$app->get('/logout', function () use ($app) {
    $app['session']->set('user', null);
    return json_encode(array('success' => true));
});


$app->get('/todo/{id}', function (Request $request, $id) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }
    $contentType = $request->headers->get('Content-Type');
    if ($id) {
        # check whether a todo list exists with the specified id
        $sql = "SELECT * FROM todos WHERE id = '$id'";
        $todo = $app['db']->fetchAssoc($sql);

        # render an error page with 404 not found
        if ($todo == null) {
            $error_details = ['code' => 404, 'detail' => 'Not Found'];
            return $app['twig']->render('error.html', ['error' => $error_details]);
        }

        # Render an error page if the user is not authorized to access to specific todo
        if ($todo['user_id'] != $user['id']) {
            $error_details = ['code' => 401, 'detail' => 'Unauthorized'];
            return $app['twig']->render('error.html', ['error' => $error_details]);
        }
        return $app['twig']->render('todo.html', [
            'todo' => $todo,
        ]);
    } else {
        $sql = "SELECT * FROM todos WHERE user_id = '${user['id']}'";
        $todos = $app['db']->fetchAll($sql);

        if (strpos($contentType, 'application/json') === false) {
            return $app['twig']->render('todos.html');
        } else {
            return json_encode($todos);
        }
    }
})
    ->value('id', null);


$app->post('/todo/add', function (Request $request) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    $user_id = $user['id'];
    $description = $request->get('description');

    $sql = "INSERT INTO todos (user_id, description) VALUES ('$user_id', '$description')";
    $app['db']->executeUpdate($sql);
    $id = $app['db']->lastInsertId();

    return json_encode(array('success' => true, 'id' => $id, 'user_id' => $user_id, 'description' => $description));
});


$app->match('/todo/delete/{id}', function ($id) use ($app) {

    $sql = "DELETE FROM todos WHERE id = '$id'";
    $app['db']->executeUpdate($sql);

    return json_encode(array('success' => true));
});

# Mark a todo as completed
$app->match('/todo/completed/{id}', function ($id) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    $sql = "UPDATE todos SET is_completed = NOT is_completed WHERE id = '$id'";
    $app['db']->executeUpdate($sql);

    return json_encode(array('success' => true));
})->value('id', null);

# Get a todo list in json format
# Commenting out for task 6
// $app->get('/todo/{id}/json', function ($id) use ($app) {
//     if (null === $user = $app['session']->get('user')) {
//         return $app->redirect('/login');
//     }
//     // Case of 404 & 401

//     $sql = "SELECT * FROM todos WHERE id = '$id'";
//     $todo = $app['db']->fetchAssoc($sql);
//     $todo_json = json_encode($todo);

//     # render an error page with 404 not found
//     if ($todo == null) {
//         $error_details = ['code' => 404, 'detail' => 'Not Found'];
//         return $app['twig']->render('error.html', ['error' => $error_details]);
//     }

//     # Render an error page if the user is not authorized to access to specific todo
//     if ($todo['user_id'] != $user['id']) {
//         $error_details = ['code' => 401, 'detail' => 'Unauthorized'];
//         return $app['twig']->render('error.html', ['error' => $error_details]);
//     }

//     return $todo_json;
// })->value('id', null);
