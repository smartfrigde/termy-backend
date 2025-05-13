package sshconn

import (
	"golang.org/x/crypto/ssh"
	"fmt"
)

type SSHClient struct {
	Client *ssh.Client
}

func Connect(user, password, host string) (*SSHClient, error) {
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

func (s *SSHClient) RunCommand(cmd string)(string, error) {
	session, err := s.Client.NewSession()
	if err != nil {
		return "", fmt.Errorf("nie można utworzyć sesji: %w", err)
	}
	defer session.Close()

	output, err := session.CombinedOutput(cmd)
	if err != nil {
		return "", fmt.Errorf("błąd wykonania komendy: %w\n%s", err, output)
	}

	return string(output), nil
}
