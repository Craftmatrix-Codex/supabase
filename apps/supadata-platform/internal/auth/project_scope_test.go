package auth

import (
	"context"
	"regexp"
	"testing"
	"time"

	sqlmock "github.com/DATA-DOG/go-sqlmock"
	"github.com/renzaspiras/supabase/apps/supadata-platform/internal/database"
	"github.com/renzaspiras/supabase/apps/supadata-platform/internal/project"
)

func TestPostgresRepositoryUsesProjectDatabaseConnection(t *testing.T) {
	defaultDB, defaultMock, err := sqlmock.New()
	if err != nil {
		t.Fatal(err)
	}
	defer defaultDB.Close()
	projectDB, projectMock, err := sqlmock.New()
	if err != nil {
		t.Fatal(err)
	}
	defer projectDB.Close()

	repository, err := NewPostgresRepository(defaultDB, "auth")
	if err != nil {
		t.Fatal(err)
	}
	projectMock.ExpectQuery(regexp.QuoteMeta(`
		SELECT id, email, role, encrypted_password, raw_app_meta_data, raw_user_meta_data, created_at, updated_at, confirmed_at
		FROM "auth".users
		WHERE lower(email) = lower($1) AND deleted_at IS NULL
		LIMIT 1`)).WithArgs("alpha@example.com").WillReturnRows(
		sqlmock.NewRows([]string{"id", "email", "role", "encrypted_password", "raw_app_meta_data", "raw_user_meta_data", "created_at", "updated_at", "confirmed_at"}).AddRow(
			"alpha-user", "alpha@example.com", "authenticated", "hash", []byte(`{}`), []byte(`{}`), time.Now(), time.Now(), nil,
		),
	)

	ctx := database.WithConnection(
		project.WithScope(context.Background(), project.Project{ID: "alpha"}),
		projectDB,
	)
	user, passwordHash, err := repository.FindUserByEmail(ctx, "alpha@example.com")
	if err != nil {
		t.Fatalf("FindUserByEmail() error = %v", err)
	}
	if user.ID != "alpha-user" || passwordHash != "hash" {
		t.Fatalf("user=%+v passwordHash=%q", user, passwordHash)
	}
	if err := projectMock.ExpectationsWereMet(); err != nil {
		t.Fatal(err)
	}
	if err := defaultMock.ExpectationsWereMet(); err != nil {
		t.Fatal(err)
	}
}
