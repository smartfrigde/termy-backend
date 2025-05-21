package ws

import (
	"github.com/google/uuid"
	"github.com/gorilla/websocket"
	"log"
	"net/http"
	"sync"
)

var (
	upgrader = websocket.Upgrader{CheckOrigin: func(r *http.Request) bool {
		return true
	}}
	clients = make(map[string]*Client)
	mu      sync.Mutex
)

func HandleWebsocket(w http.ResponseWriter, r *http.Request) {
	conn, err := upgrader.Upgrade(w, r, nil)
	if err != nil {
		http.Error(w, "Nie udało się ustanowić połączenia WebSocket", http.StatusBadRequest)
		return
	}

	sessionID := uuid.New().String()

	client := &Client{
		ID:   sessionID,
		Conn: conn,
		Send: make(chan []byte),
		Done: make(chan struct{}),
	}

	mu.Lock()
	clients[sessionID] = client
	mu.Unlock()

	log.Println("Nowy klient:", sessionID)

	go client.read()
	go client.write()

	go func() {
		<-client.Done
		log.Println("Zamykanie sesji:", client.ID)

		mu.Lock()
		delete(clients, client.ID)
		mu.Unlock()

		client.Conn.Close()
		close(client.Send)
	}()
}
