package ws

import (
	"log"
	"github.com/gorilla/websocket"
)

type Client struct {
	ID   string
	Conn *websocket.Conn
	Send chan []byte
	Done chan struct{}
}

func (c *Client) read() {
	defer c.Conn.Close()
	for {
		_, msg, err := c.Conn.ReadMessage()
		if err != nil {
			log.Println("Błąd odczytu:", err)
			break
		}
		
		log.Printf("Odebrano wiadomość: %s", msg)

		c.Send <- []byte("Odpowiedź serwera: " + string(msg))
	}
}

func (c *Client) write() {
	defer c.Conn.Close()
	for msg := range c.Send {
		err := c.Conn.WriteMessage(websocket.TextMessage, msg)
		if err != nil {
			log.Println("Błąd zapisu:", err)
			break
		}
	}
}
