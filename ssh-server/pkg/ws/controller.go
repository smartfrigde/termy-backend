package ws

import (
	"encoding/json"
	"fmt"
	"github.com/gorilla/websocket"
	"log"
	"ssh-server/pkg/sshconn"
	"strconv"
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
			log.Println("Błąd JSON:", err)
			continue
		}

		switch arrayMessage["type"] {
		case "connect":
			if arrayMessage["hostname"] != nil && arrayMessage["login"] != nil && arrayMessage["port"] != nil {
				host := fmt.Sprintf("%s:%v", arrayMessage["hostname"], arrayMessage["port"])
				login := arrayMessage["login"].(string)
				password := ""

				if arrayMessage["password"] != nil {
					password = arrayMessage["password"].(string)
				}

				fmt.Println(login, password, host)

				sshClient, err := sshconn.Connect(login, password, host)

				if err != nil {
					log.Println("SSH błąd:", err)
					c.Send <- []byte("Nie udało się połączyć z SSH")
					continue
				}

				session, stdin, stdout, err := sshClient.Connect()

				if err != nil {
					log.Println("Sesja SSH nie powiodła się:", err)
					continue
				}

				c.SSH = session
				c.Stdin = stdin
				c.Stdout = stdout

				go func() {
					buf := make([]byte, 1024)
					for {
						n, err := stdout.Read(buf)
						if err != nil {
							log.Println("Błąd stdout:", err)
							break
						}

						c.Send <- buf[:n]
					}
				}()

			}

		case "command":
			if content, ok := arrayMessage["content"].(string); ok && c.Stdin != nil {
				unquoted, err := strconv.Unquote(`"` + content + `"`)
				if err != nil {
					log.Println("Błąd unquote:", err)
					continue
				}

				_, err = c.Stdin.Write([]byte(unquoted))
				if err != nil {
					log.Println("Błąd przy pisaniu do SSH:", err)
				}

			}
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
