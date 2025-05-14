package sshconn

import (
	"golang.org/x/crypto/ssh"
	"fmt"
)

type SSHClient struct {
	Client *ssh.Client
}

func Connect(user string, password string, host string) (*SSHClient, error) {
	config := &ssh.ClientConfig{
		User: user,
		Auth: []ssh.AuthMethod{
			ssh.Password(password),
		},
	}

	client, err := ssh.Dial("tcp", host, config)
	if err != nil {
		return nil, fmt.Errorf("nie można połączyć się z SSH: %w", err)
	}

	return &SSHClient{Client: client}, nil
}