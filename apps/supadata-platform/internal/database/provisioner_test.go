package database

import (
	"context"
	"testing"

	sqlmock "github.com/DATA-DOG/go-sqlmock"
	"github.com/renzaspiras/supabase/apps/supadata-platform/internal/project"
)

func TestSharedProvisionerPingsConfiguredDatabase(t *testing.T) {
	db, mock, err := sqlmock.New(sqlmock.MonitorPingsOption(true))
	if err != nil {
		t.Fatal(err)
	}
	defer db.Close()
	mock.ExpectPing()

	router := NewRouter(nil)
	provisioner := SharedProvisioner{DB: db, Router: router}
	value := project.Project{ID: "alpha", Scope: project.ResourceScope{Database: project.DatabaseScope{Name: "supadata_alpha"}}}
	if err := provisioner.ProvisionDatabase(context.Background(), value); err != nil {
		t.Fatal(err)
	}
	if resolved, err := router.Resolve(context.Background(), value); err != nil || resolved != db {
		t.Fatalf("resolved database = %v, error = %v", resolved, err)
	}
	if _, err := router.Resolve(context.Background(), project.Project{ID: "beta"}); err == nil {
		t.Fatal("unprovisioned project must not resolve")
	}
}

func TestSharedProvisionerRejectsMissingDatabase(t *testing.T) {
	if err := (SharedProvisioner{}).ProvisionDatabase(context.Background(), project.Project{}); err != ErrSharedDatabaseMissing {
		t.Fatalf("error = %v, want %v", err, ErrSharedDatabaseMissing)
	}
}
