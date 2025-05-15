package ws

import (
	"github.com/gorilla/websocket"
	"golang.org/x/crypto/ssh"
	"io"
)

type Client struct {
	ID     string
	Conn   *websocket.Conn
	Send   chan []byte
	Done   chan struct{}

	SSH    *ssh.Session
	Stdin  io.WriteCloser
	Stdout io.Reader
}
