package sshconn

import (
	"golang.org/x/crypto/ssh"
	"fmt"
	"io"
)

type SSHClient struct {
	Client *ssh.Client
}

func (s *SSHClient) Connect() (*ssh.Session, io.WriteCloser, io.Reader, error) {
	session, err := s.Client.NewSession()
	if err != nil {
		return nil, nil, nil, fmt.Errorf("nie można utworzyć sesji: %w", err)
	}

	modes := ssh.TerminalModes{
		ssh.ECHO:          1,    
		ssh.TTY_OP_ISPEED: 14400, 
		ssh.TTY_OP_OSPEED: 14400, 
	}

	if err := session.RequestPty("xterm", 80, 40, modes); err != nil {
		session.Close()
		return nil, nil, nil, fmt.Errorf("cannot get PPY: %w", err)
	}

	stdin, err := session.StdinPipe()
	if err != nil {
		return nil, nil, nil, fmt.Errorf("stdin error: %w", err)
	}
	stdout, err := session.StdoutPipe()
	if err != nil {
		return nil, nil, nil, fmt.Errorf("stdout error: %w", err)
	}

	if err := session.Shell(); err != nil {
		return nil, nil, nil, fmt.Errorf("shell start error: %w", err)
	}

	return session, stdin, stdout, nil
}

func Connect(user, password, host string) (*SSHClient, error) {
	config := &ssh.ClientConfig{
		User: user,
		Auth: []ssh.AuthMethod{
			ssh.Password(password),
		},
		HostKeyCallback: ssh.InsecureIgnoreHostKey(),
	}

	client, err := ssh.Dial("tcp", host, config)
	if err != nil {
		return nil, fmt.Errorf("błąd połączenia SSH: %w", err)
	}

	return &SSHClient{Client: client}, nil
}