import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { colors, spacing, typography, shadow } from '../theme';

export default function Header({ title, subtitle, right, onBack, accent = null }) {
    const accentColor = accent ? (colors[accent] && colors[accent][50]) : colors.surface;
    
    return (
        <View style={[styles.container, { backgroundColor: accentColor || colors.surface }]}>
            {onBack && (
                <Icon name="chevron-left" size={28} color={colors.textPrimary} onPress={onBack} style={{ marginRight: spacing.sm, marginLeft: -spacing.xs }} />
            )}
            <View style={styles.left}>
                <Text style={styles.title} numberOfLines={1}>{title}</Text>
                {subtitle && <Text style={styles.subtitle}>{subtitle}</Text>}
            </View>
            {right && <View style={styles.right}>{right}</View>}
        </View>
    );
}

const styles = StyleSheet.create({
    container: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        paddingHorizontal: spacing.lg,
        paddingTop: spacing.xl,
        paddingBottom: spacing.lg,
        ...shadow.sm,
        zIndex: 10,
    },
    left: {
        flex: 1,
    },
    title: {
        ...typography.display,
        fontSize: 24,
        color: colors.textPrimary,
    },
    subtitle: {
        ...typography.body,
        color: colors.textSecondary,
        marginTop: 2,
    },
    right: {
        marginLeft: spacing.md,
        flexDirection: 'row',
        alignItems: 'center',
    }
});
