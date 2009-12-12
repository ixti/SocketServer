<?php
/**
 * SocketServer
 *
 * This file is part of SocketServer.
 *
 * SocketServer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * SocketServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with SocketServer. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package SocketServer
 * @link http://blog.ixti.ru/?p=116
 * @copyright Copyright (c) 2009 Aleksey V. Zapparov AKA ixti <http://ixti.ru/>
 * @license http://www.gnu.org/licenses/ GPLv3
 */


/**
 * SocketServer
 */
require_once 'SocketServer.php';


/**
 * Sample request handler
 *
 * - Return null upon 'quit' or 'exit' request
 * - Return false upon 'stop' or 'halt' request
 * - Return md5 hash of request string
 *
 * @param string $request
 * @return void
 */
function my_handler($request, $id)
{
    if (1 === preg_match('/quit|exit/i', $request)) {
        return null;
    }

    if (1 === preg_match('/stop|halt/i', $request)) {
        return false;
    }

    echo sprintf('*** Got "%s" from %d', $request, $id) . PHP_EOL;
    return md5($request) . PHP_EOL;
}


/**
 * Sample onOpen handler
 *
 * Print a message into server's console about new connection
 *
 * @param integer $id
 * @param string $addr
 * @param integer $port
 * @return void
 */
function my_open_handler($id, $addr, $port = null)
{
    echo sprintf('New connection [%d] arrived from %s:%d', $id, $addr, $port) . "\n";
}


/**
 * Sample onCleanup handler
 *
 * Print a message into server's console when client was disconnected by itself
 *
 * @param integer $id
 * @return void
 */
function my_cleanup_handler($id)
{
    echo sprintf('Connection [%d] cleaned-up', $id) . "\n";
}


/**
 * Sample onClose handler
 *
 * Print a message into server's console when client disconnects
 *
 * @param integer $id
 * @return void
 */
function my_close_handler($id)
{
    echo sprintf('Connection [%d] closed', $id) . "\n";
}


/**
 * Sample onWriteError handler
 *
 * Print a message into server's console when response write failed
 *
 * @param integer $id
 * @return void
 */
function my_write_error_handler($id)
{
    echo sprintf('Write error to [%d]', $id) . "\n";
}


try {
    $motd   = 'WELCOME TO THE SIMPLE SOCKET SERVER IN PHP' . "\n"
            . '------------------------------------------' . "\n";
    $server = new SocketServer(AF_INET, SOCK_STREAM, SOL_TCP);
    $server ->bind('0.0.0.0', 12345)
            ->setMotd($motd)
            ->setRequestHandler('my_handler')
            ->setOnOpenHandler('my_open_handler')
            ->setOnCleanupHandler('my_cleanup_handler')
            ->setOnCloseHandler('my_close_handler')
            ->setOnWriteErrorHandler('my_write_error_handler')
            ->run();
    exit(0);
} catch (Exception $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

