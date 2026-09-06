package project

import (
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"strings"
)

type DatabaseScope struct {
	Name   string `json:"name"`
	Role   string `json:"role"`
	Schema string `json:"schema"`
}

type StorageScope struct {
	Bucket  string            `json:"bucket"`
	Buckets []json.RawMessage `json:"buckets,omitempty"`
}

type ResourceScope struct {
	Database  DatabaseScope `json:"database"`
	Storage   StorageScope  `json:"storage"`
	PublicURL string        `json:"publicUrl,omitempty"`
}

func BuildScope(id, publicHost string) (ResourceScope, error) {
	if !validProjectID(id) {
		return ResourceScope{}, errors.New("invalid project id")
	}
	namespace := resourceNamespace(id)
	scope := ResourceScope{
		Database: DatabaseScope{
			Name:   "supadata_" + namespace,
			Role:   "supadata_" + namespace + "_runtime",
			Schema: "public",
		},
		Storage: StorageScope{Bucket: "supadata-" + id},
	}
	if len(scope.Database.Name) > 63 || len(scope.Database.Role) > 63 || len(scope.Storage.Bucket) > 63 {
		return ResourceScope{}, errors.New("project resource namespace is too long")
	}
	host := strings.TrimSpace(strings.TrimSuffix(publicHost, "/"))
	host = strings.TrimPrefix(host, "https://")
	host = strings.TrimPrefix(host, "http://")
	if host != "" {
		if strings.ContainsAny(host, "/?#") {
			return ResourceScope{}, errors.New("invalid public host")
		}
		scope.PublicURL = fmt.Sprintf("https://%s.%s", id, host)
	}
	return scope, nil
}

func validProjectID(id string) bool {
	if id == "" || len(id) > 48 || id[0] == '-' || id[len(id)-1] == '-' {
		return false
	}
	previousDash := false
	for _, character := range id {
		if character == '-' {
			if previousDash {
				return false
			}
			previousDash = true
			continue
		}
		isLowercase := character >= 'a' && character <= 'z'
		isDigit := character >= '0' && character <= '9'
		if !isLowercase && !isDigit {
			return false
		}
		previousDash = false
	}
	return true
}

func resourceNamespace(id string) string {
	if len(id) <= 40 {
		return strings.ReplaceAll(id, "-", "_")
	}
	digest := sha256.Sum256([]byte(id))
	return strings.ReplaceAll(id[:33], "-", "_") + "_" + hex.EncodeToString(digest[:])[:6]
}
