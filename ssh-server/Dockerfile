# syntax=docker/dockerfile:1
FROM golang:1.24 AS builder

WORKDIR /app

COPY go.mod ./
RUN go mod tidy

COPY . ./

RUN go build -o main .
FROM debian:bullseye-slim
WORKDIR /app
COPY --from=builder /app/main .
CMD ["./main"]
