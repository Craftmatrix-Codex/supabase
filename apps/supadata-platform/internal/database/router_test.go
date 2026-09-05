package database

import (
	"context"
	"testing"

	sqlmock "github.com/DATA-DOG/go-sqlmock"
	"github.com/renzaspiras/supabase/apps/supadata-platform/internal/project"
)

func TestRouterResolvesProjectDatabaseWithoutCrossProjectFallback(t *testing.T) {
	defaultDB, defaultMock, err := sqlmock.New()
	if err != nil {
		t.Fatalf("sqlmock.New(default) error = %v", err)
	}
	defer defaultDB.Close()
	alphaDB, _, err := sqlmock.New()
	if err != nil {
		t.Fatalf("sqlmock.New(alpha) error = %v", err)
	}
	defer alphaDB.Close()
	betaDB, _, err := sqlmock.New()
	if err != nil {
		t.Fatalf("sqlmock.New(beta) error = %v", err)
	}
	defer betaDB.Close()
	_ = defaultMock

	router := NewRouter(defaultDB)
	if err := router.Register("alpha", alphaDB); err != nil {
		t.Fatalf("Register(alpha) error = %v", err)
	}
	if err := router.Register("beta", betaDB); err != nil {
		t.Fatalf("Register(beta) error = %v", err)
	}

	resolvedAlpha, err := router.Resolve(context.Background(), project.Project{ID: "alpha"})
	if err != nil {
		t.Fatalf("Resolve(alpha) error = %v", err)
	}
	resolvedBeta, err := router.Resolve(context.Background(), project.Project{ID: "beta"})
	if err != nil {
		t.Fatalf("Resolve(beta) error = %v", err)
	}
	if resolvedAlpha != alphaDB || resolvedBeta != betaDB || resolvedAlpha == resolvedBeta {
		t.Fatal("project databases were not isolated")
	}
	if _, err := router.Resolve(context.Background(), project.Project{ID: "missing"}); err == nil {
		t.Fatal("unknown project fell back to the default database")
	}
}
