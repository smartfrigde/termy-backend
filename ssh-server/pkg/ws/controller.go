package ws

import (
	"encoding/json"
	"fmt"
	"github.com/gorilla/websocket"
	"log"
	"ssh-server/pkg/sshconn"
)

func (c *Client) read() {
	defer c.Conn.Close()
	for {
		_, msg, err := c.Conn.ReadMessage()
		if err != nil {
			log.Println("Błąd odczytu:", err)
			break
		}

		arrayMessage, err := getArrayFronJsonMessage(string(msg))

		if err != nil {
			return
		}

		switch arrayMessage["type"] {
			case "connect":
				if arrayMessage["hostname"] != nil && arrayMessage["login"] != nil && arrayMessage["port"] != nil {
					host := fmt.Sprintf("%s:%v", arrayMessage["hostname"], arrayMessage["port"])
					login, _ := arrayMessage["login"].(string)

					password := ""
					if arrayMessage["password"] != nil {
						password, _ = arrayMessage["password"].(string)
					}

					sshconn.Connect(login, password, host)
				}
			case "command":
				
		}
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

func getArrayFronJsonMessage(msg string) (map[string]interface{}, error) {
	var data map[string]interface{}

	err := json.Unmarshal([]byte(msg), &data)
	if err != nil {
		return nil, err
	}

	return data, nil
}
