package runtime

import (
	"context"
	"testing"

	"github.com/renzaspiras/supabase/apps/supadata-platform/internal/config"
	"github.com/renzaspiras/supabase/apps/supadata-platform/internal/project"
)

func TestOpenProjectDatabasesRejectsIncompletePerProjectConfiguration(t *testing.T) {
	cfg := config.Config{
		DatabaseMode:        "per-project",
		ProjectDatabaseURLs: map[string]string{"alpha": "postgres://alpha"},
	}
	_, err := OpenProjectDatabases(context.Background(), cfg, []project.Project{{ID: "alpha"}, {ID: "beta"}})
	if err == nil {
		t.Fatal("expected missing beta database URL error")
	}
}

func TestOpenProjectDatabasesReturnsNilWhenNoDatabaseIsConfigured(t *testing.T) {
	connections, err := OpenProjectDatabases(context.Background(), config.Config{}, nil)
	if err != nil {
		t.Fatal(err)
	}
	if connections != nil {
		t.Fatal("expected no database connections")
	}
}
