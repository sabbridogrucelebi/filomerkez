import React from 'react';
import { View, StyleSheet } from 'react-native';
import Icon from '@expo/vector-icons/MaterialCommunityIcons';
import { colors } from '../theme';

export default function EmptyIcon3D({ icon, accent = 'info', size = 64 }) {
    const accentColor = colors[accent] || { 50: colors.surfaceAlt, 100: '#E2E8F0', 700: colors.textPrimary };
    const outerSize = size * 1.5;
    const innerSize = size * 1.2;

    return (
        <View style={[styles.outer, { width: outerSize, height: outerSize, borderRadius: outerSize/2, backgroundColor: accentColor[50] }]}>
            <View style={[styles.inner, { width: innerSize, height: innerSize, borderRadius: innerSize/2, backgroundColor: accentColor[100] }]}>
                <Icon name={icon} size={size * 0.6} color={accentColor[700]} />
            </View>
        </View>
    );
}

const styles = StyleSheet.create({
    outer: {
        alignItems: 'center',
        justifyContent: 'center',
    },
    inner: {
        alignItems: 'center',
        justifyContent: 'center',
    }
});
