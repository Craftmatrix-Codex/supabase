package project

import "time"

type Project struct {
	ID               string        `json:"id"`
	Name             string        `json:"name"`
	Status           string        `json:"status"`
	Current          bool          `json:"current"`
	ConnectionString string        `json:"connectionString,omitempty"`
	DatabaseHost     string        `json:"db_host,omitempty"`
	DatabasePort     int           `json:"db_port,omitempty"`
	DatabaseName     string        `json:"db_name,omitempty"`
	DatabaseUser     string        `json:"db_user,omitempty"`
	Scope            ResourceScope `json:"scope,omitempty"`
	CreatedAt        time.Time     `json:"createdAt,omitempty"`
	Error            string        `json:"error,omitempty"`
}
