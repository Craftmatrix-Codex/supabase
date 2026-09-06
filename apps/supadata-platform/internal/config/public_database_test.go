package config

import (
	"strings"
	"testing"
)

func TestPublicDatabaseDetailsUsesExplicitSafeMetadata(t *testing.T) {
	t.Setenv("SUPADATA_PUBLIC_DATABASE_HOST", "13.140.162.195")
	t.Setenv("SUPADATA_PUBLIC_DATABASE_PORT", "5432")
	t.Setenv("SUPADATA_PUBLIC_DATABASE_NAME", "cmx")
	t.Setenv("SUPADATA_PUBLIC_DATABASE_USER", "postgres")
	t.Setenv("SUPADATA_PUBLIC_DATABASE_CONNECTION_STRING", "postgresql://postgres:real-secret@13.140.162.195:5432/cmx")

	host, port, name, user, connectionString := Load().PublicDatabaseDetails()
	if host != "13.140.162.195" || port != 5432 || name != "cmx" || user != "postgres" {
		t.Fatalf("unexpected public database details: %q %d %q %q", host, port, name, user)
	}
	if connectionString == "" || strings.Contains(connectionString, "real-secret") || !strings.Contains(connectionString, "13.140.162.195:5432/cmx") {
		t.Fatalf("connection string was not sanitized: %q", connectionString)
	}
}
