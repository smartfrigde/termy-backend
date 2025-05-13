package ws

import (
	"net/http"
	`github.com/gorilla/websocket`
);

var upgrader = websocket.Upgrader{
	ReadBufferSize:  1024,
	WriteBufferSize: 1024,
	CheckOrigin: func(r *http.Request) bool {
		// Możesz dodać tu logikę zabezpieczeń (np. CORS, token itp.)
		return true
	},
};

func handleWebsocket(httpFragment http.ResponseWriter, r *http.Request){
	connection, err := upgrader.Upgrade(httpFragment, r, nil);

	if (err != nil){
		http.Error(httpFragment, "Nie udało się ustanowić połączenia WebSocket", http.StatusBadRequest)
		return
	}

	client := &Client{
		Conn: connection,
		Send: make(chan []byte),
	};

	go client.Write();
	go client.Read();
}