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
 * @copyright Copyright (c) 2009 Aleksey V. Zapparov AKA ixti <http://ixti.ru/>
 * @license http://www.gnu.org/licenses/ GPLv3
 */


/**
 * @package SocketServer
 * @link http://blog.ixti.ru/?p=116
 * @copyright Copyright (c) 2009 Aleksey V. Zapparov AKA ixti <http://ixti.ru/>
 * @license http://www.gnu.org/licenses/ GPLv3
 */
class SocketServer
{
    /**
     * Socket resourece created by {@link socket_create()}
     *
     * @see socket_create()
     * @var resource
     */
    private $__socket;


    /**
     * Tells whenever {@link $__socket} is binded or not.
     *
     * @see SocketServer::bind()
     * @var boolean
     */
    private $__isBinded = false;


    /**
     * Handler function of incoming requests. Returned value will be sent client
     * as response message.
     *
     * @see SocketServer::setRequestHandler()
     * @var mixed
     */
    private $__handler = null;


    /**
     * Function to be called upon new connection arrives.
     *
     * @see SocketServer::setOnOpenHandler()
     * @var mixed
     */
    private $__onOpen = null;


    /**
     * Function to be called upon connection cleanup.
     *
     * @see SocketServer::setOnCleanupHandler()
     * @var mixed
     */
    private $__onCleanup = null;


    /**
     * Function to be called upon client disconnection.
     *
     * @see SocketServer::setOnCloseHandler()
     * @var mixed
     */
    private $__onClose = null;


    /**
     * Function to be called upon response write error
     *
     * @see SocketServer::setOnWriteErrorHandler()
     * @var mixed
     */
    private $__onWriteError = null;


    /**
     * Welome message to be displayed to new clients.
     *
     * @var string|null
     */
    private $__motd = null;


    /**
     * Socket read per time amount
     *
     * @see http://www.phpclasses.org/discuss/package/5758/thread/2/
     * @var integer
     */
    private $__readAmount = 2048;


    /**
     * Socket read mode
     *
     * @see http://www.phpclasses.org/discuss/package/5758/thread/2/
     * @var integer
     */
    private $__readMode = PHP_NORMAL_READ;


    /**
     * Auto-close after response mode.
     *
     * @link http://www.phpclasses.org/discuss/package/5758/thread/3/
     * @var boolean
     */
    private $__autoClose = false;



    /**
     * Class constructor.
     *
     * Creates a socket resource. Simple wraper of {@link socket_create()},
     * which creates a resource and keep it as private property.
     *
     * Example:
     * <code>
     * $protocol = getprotobyname('udp');
     * $server   = new SocketServer(AF_INET, SOCK_DGRAM, $protocol);
     * </code>
     *
     * Please reffer to {@link socket_create()} manual for more details, as this
     * is just a wrapper of that function.
     *
     * @see socket_create()
     * @throws Exception If {@link socket_create()} failed
     * @param integer $domain   Protocol family to be used by the socket.
     * @param integer $type     Type of communication to be used by the socket.
     * @param integer $protocol Protocol within the specified $domain.
     */
    public function __construct($domain, $type, $protocol)
    {
        $this->__socket = @socket_create($domain, $type, $protocol);

        if (false === $this->__socket) {
            $this->__raiseError();
        }
    }


    /**
     * Class destructor
     *
     * Close socket if it was created.
     *
     * @see socket_close()
     */
    public function  __destruct()
    {
        @socket_close($this->__socket);
    }


    /**
     * Set welcome message for new clients.
     *
     * @param string|null $msg
     * @return SocketServer self-reference
     */
    public function setMotd($msg)
    {
        $msg = trim($msg);
        $this->__motd = (0 !== $msg) ? "\n" . $msg . "\n" : null;
        return $this;
    }


    /**
     * Throws {@link Exception} with last socket error or specified message.
     *
     * Close socket, if it was opened and then throws {@link Exception}. If
     * $msg is not specified or NULL, last sockt error will be used as message.
     *
     * @throws Exception
     * @param string $msg (optional)
     */
    private function __raiseError($msg = null)
    {
        if (null === $msg) {
            $msg = socket_strerror(socket_last_error());
        }
        
        throw new Exception($msg);
    }


    /**
     * Sets socket_read limit
     *
     * @param integer $limit
     */
    public function setReadAmount($limit)
    {
        $this->__readAmount = $limit * 1;
    }


    /**
     * Sets socket_read mode.
     *
     * @see http://www.phpclasses.org/discuss/package/5758/thread/2/
     * @param integer $mode PHP_NORMAL_READ or PHP_BINARY_READ
     */
    public function setReadMode($mode)
    {
        if (PHP_NORMAL_READ !== $mode && PHP_BINARY_READ !== $mode) {
            $this->__raiseError('Unknown read mode.');
        }
    }


    /**
     * Sets auto-close after response mode.
     *
     * @link http://www.phpclasses.org/discuss/package/5758/thread/3/
     * @param boolean $autoClose
     */
    public function setAutoClose($autoClose = true)
    {
        $this->__autoClose = (boolean) $autoClose;
    }


    /**
     * Binds a name to a socket.
     *
     * Binds the name given in $address to the socket. This has to be done
     * before starting server with {@link SocketServer::run()}.
     *
     * @link socket_bind()
     * @throws Exception If {@link socket_bind()} failed
     * @param string $address Address name to be binded to socket.
     *        - If the socket is of the AF_INET family, the address is an IP in
     *          dotted-quad notation (e.g. 127.0.0.1).
     *        - If the socket is of the AF_UNIX family, the address is the path
     *          of a Unix-domain socket (e.g. /tmp/my.sock).
     * @param integer $port (optional) The port parameter is only used when
     *        connecting to an AF_INET socket, and designates the port on the
     *        remote host to which a connection should be made.
     * @return SocketServer self-reference
     */
    public function bind($address, $port = null)
    {
        if (false === @socket_bind($this->__socket, $address, $port)) {
            $this->__raiseError();
        }

        $this->__isBinded = true;
        return $this;
    }


    /**
     * Run server.
     *
     * Calls {@link socket_listen()} and then run main daemon loop. Please refer
     * to {@link socket_listen()} about $backlog argument.
     *
     * Bind socket with {@link SocketServer::bind()} method and set request's
     * handler with {@link SocketServer::setRequestHandler()} before running a
     * server.
     *
     * @see SocketServer::bind()
     * @see SocketServer::setRequestHandler()
     * @throws Exception If {@link $__handler} was not set
     * @throws Exception If socket was not binded
     * @throws Exception If {@link socket_listen()} failed
     * @param integer $backlog (optional) A maximum of incoming connections.
     * @return void
     */
    public function run($backlog = null)
    {
        if (null === $this->__handler) {
            $this->__raiseError('Handler must be set first');
        }

        if (false === $this->__isBinded) {
            $this->__raiseError('Socket must be binded first');
        }

        if (false === @socket_listen($this->__socket, $backlog)) {
            $this->__raiseError();
        }

        $this->__run();
    }


    /**
     * Registers request handler.
     *
     * $handler will be called with passing request as the only argument.
     *
     * - Client will be disconnected upon $func will return NULL.
     * - Server will be stopped upon $func will return boolean false.
     * - Else returned value will be sent as a response.
     *
     * @throws Exception If specified $func can't be called
     * @param mixed $func Request handler function or method. Can be either
     *        the name of a function stored in a string variable, or an object
     *        and the name of a method within the object, like this:
     *        array($SomeObject, 'MethodName')
     * @return SocketServer self-reference
     */
    public function setRequestHandler($func)
    {
        if ( ! is_callable($func)) {
            $this->__raiseError('Request handler is not callable.');
        }

        $this->__handler = $func;
        return $this;
    }


    /**
     * Sets handler to be called upon new connection.
     *
     * Function will be called with passing it three arguments:
     *  - integer: Connection id, to determine which connection is requesting
     *    handler call
     *  - string: Socket's address name of remote end, e.g. '127.0.0.1'
     *  - integer: (optional) Socket's port in case of INET* socket
     *
     * Example:
     * <code>
     * function conn_open_handler($id, $addr, $port = null)
     * {
     *     // ...
     * }
     * </code>
     *
     * @throws Exception If specified $func can't be called
     * @param mixed $func onOpen handler function or method. Can be either
     *        the name of a function stored in a string variable, or an object
     *        and the name of a method within the object, like this:
     *        array($SomeObject, 'MethodName')
     * @return SocketServer self-reference
     */
    public function setOnOpenHandler($func)
    {
        if ( ! is_callable($func)) {
            $this->__raiseError('onOpen handler is not callable.');
        }

        $this->__onOpen = $func;
        return $this;
    }


    /**
     * Open handler executor.
     *
     * Will execute open handler with specified resource id, address, and port.
     *
     * @see SocketServer::setOnOpenHandler()
     * @param integer $id
     * @param resource $socket
     * @return void
     */
    private function __open($id, $socket)
    {
        if (null !== $this->__onOpen) {
            $addr = null;
            $port = null;
            socket_getpeername($socket, $addr, $port);
            return call_user_func($this->__onOpen, $id, $addr, $port);
        }
    }


    /**
     * Sets handler to be called upon pool cleanup.
     *
     * Function will be called with passing only one param - connection id.
     *
     * Example:
     * <code>
     * function conn_cleanup_handler($id)
     * {
     *     // ...
     * }
     * </code>
     *
     * @throws Exception If specified $func can't be called
     * @param mixed $func onCleanup handler function or method. Can be either
     *        the name of a function stored in a string variable, or an object
     *        and the name of a method within the object, like this:
     *        array($SomeObject, 'MethodName')
     * @return SocketServer self-reference
     */
    public function setOnCleanupHandler($func)
    {
        if ( ! is_callable($func)) {
            $this->__raiseError('onCleanup handler is not callable.');
        }

        $this->__onCleanup = $func;
        return $this;
    }


    /**
     * Cleanup handler executor.
     *
     * Will execute cleanup handler with specified resource id.
     *
     * @see SocketServer::setOnCleanupHandler()
     * @param integer $id
     * @return void
     */
    private function __cleanup($id)
    {
        if (null !== $this->__onCleanup) {
            call_user_func($this->__onCleanup, $id);
        }
    }


    /**
     * Sets handler to be called after closing a connection with client.
     *
     * Function will be called with passing only one param - connection id.
     *
     * Example:
     * <code>
     * function conn_close_handler($id)
     * {
     *     // ...
     * }
     * </code>
     *
     * @throws Exception If specified $func can't be called
     * @param mixed $func onClose handler function or method. Can be either
     *        the name of a function stored in a string variable, or an object
     *        and the name of a method within the object, like this:
     *        array($SomeObject, 'MethodName')
     * @return SocketServer self-reference
     */
    public function setOnCloseHandler($func)
    {
        if ( ! is_callable($func)) {
            $this->__raiseError('onClose handler is not callable.');
        }

        $this->__onClose = $func;
        return $this;
    }


    /**
     * Close handler executor.
     *
     * Will execute close handler with specified resource id.
     *
     * @see SocketServer::setOnCloseHandler()
     * @param integer $id
     * @return void
     */
    private function __close($id)
    {
        if (null !== $this->__onClose) {
            call_user_func($this->__onClose, $id);
        }
    }


    /**
     * Sets handler to be called upon response write error.
     *
     * Function will be called with passing only one param - connection id.
     *
     * Example:
     * <code>
     * function conn_write_error_handler($id)
     * {
     *     // ...
     * }
     * </code>
     *
     * @throws Exception If specified $func can't be called
     * @param mixed $func onWriteError handler function or method. Can be
     *        either the name of a function stored in a string variable, or
     *        an object and the name of a method within the object, like this:
     *        array($SomeObject, 'MethodName')
     * @return SocketServer self-reference
     */
    public function setOnWriteErrorHandler($func)
    {
        if ( ! is_callable($func)) {
            $this->__raiseError('onWriteError handler is not callable.');
        }

        $this->__onWriteError = $func;
        return $this;
    }


    /**
     * Write error handler executor.
     *
     * Will execute write error handler with specified resource id.
     *
     * @see SocketServer::setOnWriteErrorHandler()
     * @param integer $id
     * @return void
     */
    public function __writeError($id)
    {
        if (null !== $this->__onWriteError) {
            call_user_func($this->__onWriteError, $id);
        }
    }


    /**
     * Server's main loop.
     *
     * Taken from first version as it was described on my blog and leaved almost
     * untouched :))
     *
     * @link http://blog.ixti.ru/?p=105 Socket reader in PHP
     */
    private function __run()
    {
        // Client connections' pool
        $pool = array($this->__socket);

        // Main cycle
        while (is_resource($this->__socket)) {
            // Clean-up pool
            foreach ($pool as $conn_id => $conn) {
                if ( ! is_resource($conn)) {
                    $this->__cleanup($conn_id);
                    unset($pool[$conn_id]);
                }
            }

            // Create a copy of pool for socket_select()
            $active = $pool;

            // Halt execution if socket_select() failed
            if (false === socket_select($active, $w = null, $e = null, null)) {
                $this->__raiseError();
            }

            // Register new client in the pool
            if (in_array($this->__socket, $active)) {
                $conn = socket_accept($this->__socket);
                if (is_resource($conn)) {
                    if (null !== $this->__motd) {
                        // Send welcome message
                        socket_write($conn, $this->__motd, strlen($this->__motd));
                    }

                    $conn_id = (integer) $conn;

                    if ($this->__open($conn_id, $conn)) {
                        $pool[$conn_id] = $conn;
                    } else {
                        $this->__close($conn_id);
                        @socket_close($conn);
                    }
                }
                unset($active[array_search($this->__socket, $active)]);
            }

            // Handle every active client
            foreach ($active as $conn) {
                $conn_id = (integer) $conn;
                $request = @socket_read($conn, $this->__readAmount, $this->__readMode);

                // If connection is closed, mark it for cleanup and continue
                if (false === $request) {
                    $pool[$conn_id] = false;
                    continue;
                }

                $request  = trim($request);

                // Skip to next if client tells nothing
                if (0 == strlen($request)) {
                    continue;
                }

                $response = call_user_func($this->__handler, $request, $conn_id);
                
                // Request handler asks to close conection
                if (null === $response) {
                    socket_close($conn);
                    $this->__close($conn_id);

                    unset($pool[$conn_id]);
                    continue;
                }

                // Request handler asks to shutdown server
                if (false === $response) {
                    // Tell everyone that server is shuting down
                    foreach ($pool as $conn) {
                        if ($this->__socket !== $conn) {
                            $msg = '*** Server is shuting down by request' . "\n";
                            @socket_write($conn, $msg, strlen($msg));
                            @socket_close($conn);
                        }
                    }

                    $this->__destruct();
                    return;
                }
                
                $test = @socket_write($conn, $response, strlen($response));
                if (false === $test) {
                    $this->__writeError($conn_id);
                }

                if ($this->__autoClose) {
                    @socket_close($conn);
                }
            }
        }
    }
}
