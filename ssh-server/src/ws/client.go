package ws

import (
	"log"

	"github.com/gorilla/websocket"
)

type Client struct {
	Conn *websocket.Conn
	Send chan []byte
}

func (c *Client) Read() {
	defer c.Conn.Close()
	for {
		_, msg, err := c.Conn.ReadMessage()
		if err != nil {
			log.Println("Błąd odczytu:", err)
			break
		}
		log.Printf("Odebrano wiadomość: %s", msg)

		// Tutaj możesz np. wysłać polecenie SSH i zwrócić wynik
		c.Send <- []byte("Odpowiedź serwera: " + string(msg))
	}
}

func (c *Client) Write() {
	defer c.Conn.Close()
	for msg := range c.Send {
		err := c.Conn.WriteMessage(websocket.TextMessage, msg)
		if err != nil {
			log.Println("Błąd zapisu:", err)
			break
		}
	}
}
