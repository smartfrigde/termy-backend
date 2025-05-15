package main

import (
	"fmt"
	"log"
	"net/http"
	"ssh-server/pkg/ws"
)

func main() {
	http.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
		fmt.Fprintln(w, "To jest zwykły endpoint HTTP")
	})

	http.HandleFunc("/ws", ws.HandleWebsocket)

	log.Println("Serwer nasłuchuje na porcie :8080 (HTTP i WS)")
	err := http.ListenAndServe(":8080", nil)
	
	if err != nil {
		log.Fatal("Błąd serwera:", err)
	}
}
